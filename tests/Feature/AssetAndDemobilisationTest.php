<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsMinimalOrg;
use Tests\TestCase;

/**
 * Acceptance criterion 10 (section 13): site demobilisation tracks asset
 * returns and outstanding actions without deleting project history.
 */
class AssetAndDemobilisationTest extends TestCase
{
    use RefreshDatabase, SeedsMinimalOrg;

    public function test_demobilisation_checklist_tracks_outstanding_actions_and_asset_events_are_never_deleted(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $project = $this->makeProject($company, ['lifecycle_stage' => 'demobilising']);
        $site = $this->makeSite($company);
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory(['name' => 'Site closure', 'type' => 'service_request']);
        $itAgent = $this->makeUser();
        $this->grant($itAgent, 'it_agent', 'company', $company->id);

        $asset = Asset::create([
            'tag' => 'DEMO-0001', 'type' => 'Laptop', 'company_id' => $company->id, 'site_id' => $site->id,
            'assigned_user_id' => $itAgent->id, 'status' => 'assigned',
        ]);

        $ticket = app(TicketService::class)->create($itAgent, [
            'company_id' => $company->id, 'project_id' => $project->id, 'site_id' => $site->id,
            'category_id' => $category->id, 'type' => 'service_request',
            'impact' => 'low', 'urgency' => 'low',
            'summary' => 'Demobilise site office', 'description' => 'Phase 1 complete.',
        ], (string) Str::uuid());

        foreach (config('itsm.request_templates.site_demobilisation.checklist') as $item) {
            $ticket->checklistTasks()->create(['name' => $item['name'], 'assigned_role' => $item['assigned_role']]);
        }

        $this->assertGreaterThan(0, $ticket->checklistTasks()->count());
        $this->assertSame(0, $ticket->checklistTasks()->where('status', 'completed')->count());

        // Return the asset: a real custody event is appended, never overwriting history.
        $asset->events()->create([
            'event_type' => 'returned', 'from_user_id' => $itAgent->id, 'to_user_id' => null,
            'actor_id' => $itAgent->id, 'occurred_at' => now(),
        ]);
        $asset->update(['status' => 'in_stock', 'assigned_user_id' => null]);

        $returnTask = $ticket->checklistTasks()->where('name', 'like', 'Return%')->first();
        $this->actingAs($itAgent)->post(route('tickets.checklist.complete', [$ticket, $returnTask]), [
            'evidence_note' => 'Laptop returned to IT store',
        ])->assertRedirect();

        $ticket->refresh();
        $completed = $ticket->checklistTasks()->where('status', 'completed')->count();
        $outstanding = $ticket->checklistTasks()->where('status', 'pending')->count();

        $this->assertSame(1, $completed);
        $this->assertGreaterThan(0, $outstanding, 'Outstanding demobilisation tasks must remain visible, not be hidden or auto-completed.');

        // Project closure/demobilisation must not delete the asset's custody history.
        $this->assertSame(1, $asset->events()->count());
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'lifecycle_stage' => 'demobilising']);
    }
}
