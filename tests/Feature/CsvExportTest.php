<?php

namespace Tests\Feature;

use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsMinimalOrg;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase, SeedsMinimalOrg;

    public function test_export_only_contains_tickets_the_user_can_see_and_neutralises_formula_injection(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $this->makeSlaPolicies($calendar, $companyA);
        $this->makeSlaPolicies($calendar, $companyB);
        $category = $this->makeCategory();
        $requesterA = $this->makeUser();
        $requesterB = $this->makeUser();
        $service = app(TicketService::class);

        $ticketA = $service->create($requesterA, [
            'company_id' => $companyA->id, 'category_id' => $category->id, 'type' => 'incident',
            'impact' => 'low', 'urgency' => 'low',
            'summary' => '=cmd|"/c calc"!A1', // a formula-injection attempt
            'description' => 'Test',
        ], (string) Str::uuid());

        $ticketB = $service->create($requesterB, [
            'company_id' => $companyB->id, 'category_id' => $category->id, 'type' => 'incident',
            'impact' => 'low', 'urgency' => 'low', 'summary' => 'Unrelated ticket in company B', 'description' => 'Test',
        ], (string) Str::uuid());

        $response = $this->actingAs($requesterA)->get(route('tickets.export'));
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString($ticketA->ticket_number, $csv);
        $this->assertStringNotContainsString($ticketB->ticket_number, $csv, 'Export must not leak tickets outside the requester\'s own scope.');
        $this->assertStringContainsString("'=cmd", $csv, 'A leading = must be neutralised with a leading apostrophe.');
    }
}
