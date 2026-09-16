<?php

namespace Tests\Feature;

use App\Services\PriorityCalculator;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsMinimalOrg;
use Tests\TestCase;

/**
 * Acceptance criteria 1, 3, 8 (section 13).
 */
class PriorityAndSlaTest extends TestCase
{
    use RefreshDatabase, SeedsMinimalOrg;

    public function test_site_wide_outage_is_priority_p1(): void
    {
        $this->makePriorityMatrix();

        $priority = app(PriorityCalculator::class)->calculate('high', 'high');

        $this->assertSame('P1', $priority);
    }

    public function test_bim_licence_deadline_does_not_bypass_matrix_derived_priority(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();

        $ticket = app(TicketService::class)->create($requester, [
            'company_id' => $company->id,
            'category_id' => $category->id,
            'type' => 'incident',
            'impact' => 'medium',
            'urgency' => 'medium',
            'summary' => 'Revit licence checkout failing before tender submission',
            'description' => 'Test',
            'deadline_at' => now()->addHours(6),
            'deadline_reason' => 'Tender submission deadline',
        ], (string) Str::uuid());

        // medium/medium -> P3 per the demo matrix, not automatically critical
        // just because a deadline was supplied.
        $this->assertSame('P3', $ticket->priority);
        $this->assertNotNull($ticket->deadline_at);
    }

    public function test_sla_target_skips_weekends_and_uses_business_hours(): void
    {
        $calendar = $this->makeCalendar(); // Mon-Fri 08:30-17:30 Asia/Singapore

        // Friday 16:00 SGT: 90 min remain before the 17:30 close, leaving
        // 150 min of the 4-hour target to carry into Monday (skipping the
        // entire weekend), landing at 08:30 + 2h30m = 11:00 Monday SGT.
        $friday4pm = Carbon::parse('2026-09-18 16:00:00', 'Asia/Singapore');
        $target = $calendar->addBusinessMinutes($friday4pm, 240);

        $this->assertSame('2026-09-21 11:00:00', $target->setTimezone('Asia/Singapore')->format('Y-m-d H:i:s'));
    }

    public function test_sla_target_skips_configured_holiday(): void
    {
        $calendar = $this->makeCalendar();
        $calendar->holidays()->create(['date' => '2026-09-17', 'name' => 'Test Holiday']); // the Thursday right after our start point

        // Wednesday 16:00 SGT: 90 min remain before 17:30, leaving 30 min of
        // the 120-min target. Thursday the 17th is a holiday, so it carries
        // to Friday 08:30 + 30min = 09:00, not Thursday at all.
        $wed4pm = Carbon::parse('2026-09-16 16:00:00', 'Asia/Singapore');
        $target = $calendar->addBusinessMinutes($wed4pm, 120);

        $this->assertSame('2026-09-18 09:00:00', $target->setTimezone('Asia/Singapore')->format('Y-m-d H:i:s'));
    }

    public function test_waiting_requester_pauses_restoration_clock_but_waiting_vendor_does_not(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();
        $agent = $this->makeUser();
        $service = app(TicketService::class);

        $ticket = $service->create($requester, [
            'company_id' => $company->id, 'category_id' => $category->id, 'type' => 'incident',
            'impact' => 'low', 'urgency' => 'low', 'summary' => 'Test', 'description' => 'Test',
        ], (string) Str::uuid());

        $service->assign($ticket, $agent, $agent);
        $service->transition($ticket, $agent, 'waiting_requester');

        $clock = $ticket->slaClocks()->where('metric', 'restoration')->first();
        $this->assertTrue($clock->isPaused());

        $service->transition($ticket, $agent, 'in_progress');
        $clock->refresh();
        $this->assertFalse($clock->isPaused());
        $this->assertGreaterThan(0, $clock->paused_seconds);

        // Now check waiting_vendor does NOT pause.
        $service->transition($ticket, $agent, 'waiting_vendor');
        $clock->refresh();
        $this->assertFalse($clock->isPaused(), 'Vendor-waiting must keep the restoration clock running.');
    }

    public function test_reopening_a_resolved_ticket_increments_reopen_count_without_erasing_history(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();
        $agent = $this->makeUser();
        $service = app(TicketService::class);

        $ticket = $service->create($requester, [
            'company_id' => $company->id, 'category_id' => $category->id, 'type' => 'incident',
            'impact' => 'low', 'urgency' => 'low', 'summary' => 'Test', 'description' => 'Test',
        ], (string) Str::uuid());

        $service->assign($ticket, $agent, $agent);
        $service->transition($ticket, $agent, 'in_progress');
        $ticket->update(['resolution_code' => 'Fixed', 'resolution_notes' => 'Done']);
        $service->transition($ticket, $agent, 'resolved');
        $this->assertSame(0, $ticket->fresh()->reopen_count);

        $service->transition($ticket, $requester, 'in_progress', 'Recurred');
        $ticket->refresh();

        $this->assertSame(1, $ticket->reopen_count);
        // created, assignment, in_progress, resolved, reopened in_progress.
        $this->assertSame(5, $ticket->events()->count());
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();
        $service = app(TicketService::class);

        $ticket = $service->create($requester, [
            'company_id' => $company->id, 'category_id' => $category->id, 'type' => 'incident',
            'impact' => 'low', 'urgency' => 'low', 'summary' => 'Test', 'description' => 'Test',
        ], (string) Str::uuid());

        $this->expectException(\InvalidArgumentException::class);
        $service->transition($ticket, $requester, 'closed'); // new -> closed is not a legal transition
    }
}
