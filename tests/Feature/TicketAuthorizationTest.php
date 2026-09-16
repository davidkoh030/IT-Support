<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ExternalAccessGrant;
use App\Models\Ticket;
use App\Models\Vendor;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsMinimalOrg;
use Tests\TestCase;

/**
 * Acceptance criteria 4, 5, 7 (section 13).
 */
class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase, SeedsMinimalOrg;

    private function baseTicket(array $overrides = [])
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();

        $ticket = app(TicketService::class)->create($requester, array_merge([
            'company_id' => $company->id, 'category_id' => $category->id, 'type' => 'incident',
            'impact' => 'low', 'urgency' => 'low', 'summary' => 'Test', 'description' => 'Test',
        ], $overrides), (string) Str::uuid());

        return [$ticket, $requester, $company];
    }

    public function test_a_requester_cannot_view_another_users_ticket_by_url(): void
    {
        [$ticket] = $this->baseTicket();
        $otherUser = $this->makeUser();

        $response = $this->actingAs($otherUser)->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_project_manager_cannot_view_a_restricted_ticket_in_their_own_project_scope(): void
    {
        [$ticket, , $company] = $this->baseTicket(['restricted' => true]);
        $project = $this->makeProject($company);
        $ticket->update(['project_id' => $project->id]);

        $pm = $this->makeUser();
        $this->grant($pm, 'project_manager', 'project', $project->id);

        $response = $this->actingAs($pm)->get(route('tickets.show', $ticket));
        $response->assertForbidden();

        // Sanity check: the same PM CAN see a non-restricted ticket in their project.
        [$normalTicket] = $this->baseTicket();
        $normalTicket->update(['project_id' => $project->id, 'company_id' => $company->id]);
        $this->actingAs($pm)->get(route('tickets.show', $normalTicket))->assertOk();
    }

    public function test_it_agent_can_view_restricted_ticket_in_their_company_scope(): void
    {
        [$ticket, , $company] = $this->baseTicket(['restricted' => true]);
        $agent = $this->makeUser();
        $this->grant($agent, 'it_agent', 'company', $company->id);

        $this->actingAs($agent)->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_internal_notes_are_hidden_from_the_requester(): void
    {
        [$ticket, $requester, $company] = $this->baseTicket();
        $agent = $this->makeUser();
        $this->grant($agent, 'it_agent', 'company', $company->id);

        app(TicketService::class)->addComment($ticket, $agent, 'Internal-only diagnostic note', 'internal');
        app(TicketService::class)->addComment($ticket, $agent, 'Public update for the requester', 'public');

        $requesterView = $this->actingAs($requester)->get(route('tickets.show', $ticket));
        $requesterView->assertOk();
        $requesterView->assertDontSee('Internal-only diagnostic note');
        $requesterView->assertSee('Public update for the requester');

        $agentView = $this->actingAs($agent)->get(route('tickets.show', $ticket));
        $agentView->assertSee('Internal-only diagnostic note');
    }

    public function test_a_vendor_can_only_see_assigned_or_shared_tickets(): void
    {
        [$ticket] = $this->baseTicket();
        [$otherTicket] = $this->baseTicket();

        $vendor = Vendor::create(['name' => 'Test Vendor']);
        $vendorUser = $this->makeUser(['vendor_id' => $vendor->id]);
        $ticket->update(['vendor_id' => $vendor->id]);

        $this->actingAs($vendorUser)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($vendorUser)->get(route('tickets.show', $otherTicket))->assertForbidden();

        $otherTicket->shares()->create(['user_id' => $vendorUser->id]);
        $this->actingAs($vendorUser)->get(route('tickets.show', $otherTicket))->assertOk();
    }

    public function test_auditor_scope_is_read_only_and_time_bound(): void
    {
        [$ticket, , $company] = $this->baseTicket();
        $project = $this->makeProject($company);
        $ticket->update(['project_id' => $project->id]);

        $expiredAuditor = $this->makeUser();
        $this->grant($expiredAuditor, 'auditor', 'project', $project->id, [
            'effective_date' => now()->subDays(30)->toDateString(),
            'expiry_date' => now()->subDays(1)->toDateString(),
        ]);

        // Expired scope: no access.
        $this->actingAs($expiredAuditor)->get(route('tickets.show', $ticket))->assertForbidden();

        $activeAuditor = $this->makeUser();
        $this->grant($activeAuditor, 'auditor', 'project', $project->id, [
            'effective_date' => now()->subDays(5)->toDateString(),
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);
        $this->actingAs($activeAuditor)->get(route('tickets.show', $ticket))->assertOk();

        // Read-only: an auditor cannot transition the ticket.
        $this->actingAs($activeAuditor)
            ->post(route('tickets.transition', $ticket), ['status' => 'triaged'])
            ->assertForbidden();
    }

    public function test_project_transfer_and_membership_expiry_revokes_access(): void
    {
        [, , $company] = $this->baseTicket();
        $project = $this->makeProject($company);
        $member = $this->makeUser();

        $grant = $this->grant($member, 'member', 'project', $project->id, [
            'effective_date' => now()->subDays(60)->toDateString(),
        ]);

        $this->assertTrue($grant->fresh()->isActive());

        // Simulate a transfer: expire the old membership.
        $grant->update(['expiry_date' => now()->subDay()->toDateString()]);

        $this->assertFalse($grant->fresh()->isActive());
        $this->assertFalse($member->hasScopedRole('member', 'project', $project->id));
    }

    public function test_external_access_overdue_revocation_remains_visible_until_evidence_recorded(): void
    {
        [$ticket] = $this->baseTicket();
        $sponsor = $this->makeUser();

        $grant = ExternalAccessGrant::create([
            'ticket_id' => $ticket->id,
            'sponsor_user_id' => $sponsor->id,
            'external_party_name' => 'External Consultant',
            'resource' => 'CDE folder',
            'access_level' => 'Read',
            'expiry_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertTrue($grant->isOverdueForRevocation());

        $grant->update(['revoked_at' => now(), 'revocation_evidence' => 'Access removed from ACC on '.now()->toDateString()]);

        $this->assertFalse($grant->fresh()->isOverdueForRevocation());
    }

    public function test_self_approval_is_rejected_even_via_direct_endpoint(): void
    {
        [$ticket, $requester] = $this->baseTicket();

        $approval = Approval::create([
            'approvable_type' => Ticket::class,
            'approvable_id' => $ticket->id,
            'approver_role' => 'manager',
            'approver_user_id' => $requester->id, // misconfigured: approver is the requester
        ]);

        $response = $this->actingAs($requester)->post(route('tickets.approvals.decide', [$ticket, $approval]), [
            'decision' => 'approved',
        ]);

        $response->assertForbidden();
        $this->assertSame('pending', $approval->fresh()->decision);
    }

    public function test_unauthorised_approver_cannot_decide_and_rejected_request_cannot_be_resolved(): void
    {
        [$ticket, , $company] = $this->baseTicket();
        $realApprover = $this->makeUser();
        $bystander = $this->makeUser();
        $agent = $this->makeUser();
        $this->grant($agent, 'it_agent', 'company', $company->id);

        $approval = Approval::create([
            'approvable_type' => Ticket::class,
            'approvable_id' => $ticket->id,
            'approver_role' => 'manager',
            'approver_user_id' => $realApprover->id,
        ]);
        $ticket->update(['approval_state' => 'pending']);

        $this->actingAs($bystander)
            ->post(route('tickets.approvals.decide', [$ticket, $approval]), ['decision' => 'approved'])
            ->assertForbidden();

        $this->actingAs($realApprover)
            ->post(route('tickets.approvals.decide', [$ticket, $approval]), ['decision' => 'rejected', 'reason' => 'Not needed'])
            ->assertRedirect();

        $this->assertSame('rejected', $ticket->fresh()->approval_state);

        // A rejected request cannot be resolved through the transition endpoint.
        app(TicketService::class)->assign($ticket, $agent, $agent);
        $response = $this->actingAs($agent)->post(route('tickets.transition', $ticket), [
            'status' => 'resolved',
            'resolution_code' => 'Fixed',
            'resolution_notes' => 'Done',
        ]);

        $response->assertStatus(422);
    }
}
