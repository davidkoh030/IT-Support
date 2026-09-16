<?php

namespace Tests\Feature;

use App\Notifications\SlaBreachWarning;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsMinimalOrg;
use Tests\TestCase;

/**
 * Acceptance criterion 8 (section 13): re-running the SLA worker does not
 * duplicate escalation messages.
 */
class SlaScanCommandTest extends TestCase
{
    use RefreshDatabase, SeedsMinimalOrg;

    public function test_rerunning_sla_scan_does_not_send_duplicate_breach_notifications(): void
    {
        Notification::fake();

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

        // Force the restoration clock into the past to simulate a breach.
        $ticket->slaClocks()->where('metric', 'restoration')->update(['target_at' => now()->subHour()]);

        $this->artisan('sla:scan')->assertSuccessful();
        $this->artisan('sla:scan')->assertSuccessful();
        $this->artisan('sla:scan')->assertSuccessful();

        Notification::assertSentToTimes($agent, SlaBreachWarning::class, 1);
    }
}
