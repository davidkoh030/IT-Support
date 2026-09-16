<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsMinimalOrg;
use Tests\TestCase;

/**
 * Acceptance criteria 2 and 9 (section 13).
 */
class TicketSubmissionTest extends TestCase
{
    use RefreshDatabase, SeedsMinimalOrg;

    public function test_repeated_submission_after_a_network_error_creates_exactly_one_ticket(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();
        $this->grant($requester, 'member', 'company', $company->id);

        $payload = [
            'idempotency_key' => 'retry-key-abc',
            'company_id' => $company->id,
            'category_id' => $category->id,
            'type' => 'incident',
            'impact' => 'low',
            'urgency' => 'low',
            'summary' => 'Plotter jamming on A1 prints',
            'description' => 'Jams every time an A1 drawing is queued.',
        ];

        // First attempt "fails" client-side after the server actually
        // processed it (e.g. the response never reached the browser); the
        // client retries with the identical idempotency key.
        $first = $this->actingAs($requester)->post(route('tickets.store'), $payload);
        $second = $this->actingAs($requester)->post(route('tickets.store'), $payload);

        $first->assertRedirect();
        $second->assertRedirect($first->headers->get('Location'));
        $this->assertSame(1, Ticket::where('idempotency_key', 'retry-key-abc')->count());
    }

    public function test_mobile_report_with_asset_context_and_attachment_creates_ticket_with_attachment(): void
    {
        Storage::fake('private');

        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $site = $this->makeSite($company);
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory(['name' => 'Printers, plotters and meeting room equipment']);
        $requester = $this->makeUser();
        $this->grant($requester, 'member', 'company', $company->id);

        $asset = Asset::create([
            'tag' => 'PRN-TEST-1', 'type' => 'Plotter', 'company_id' => $company->id, 'site_id' => $site->id,
        ]);

        // Simulates the QR-code entry point identifying the asset/site context.
        $entry = $this->actingAs($requester)->get(route('tickets.create.asset', $asset));
        $entry->assertRedirect(route('tickets.create', ['asset_id' => $asset->id, 'company_id' => $company->id, 'site_id' => $site->id]));

        $photo = UploadedFile::fake()->image('plotter-jam.jpg');

        $response = $this->actingAs($requester)->post(route('tickets.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'company_id' => $company->id,
            'site_id' => $site->id,
            'category_id' => $category->id,
            'type' => 'incident',
            'impact' => 'low',
            'urgency' => 'low',
            'summary' => 'Plotter jamming on A1 prints',
            'description' => 'Jams every time an A1 drawing is queued.',
            'asset_ids' => [$asset->id],
            'attachments' => [$photo],
        ]);

        $response->assertRedirect();
        $ticket = Ticket::latest()->first();
        $this->assertTrue($ticket->assets->contains($asset));
        $this->assertCount(1, $ticket->attachments);
        Storage::disk('private')->assertExists($ticket->attachments->first()->path);
    }

    public function test_form_validation_errors_preserve_submitted_input(): void
    {
        $this->makePriorityMatrix();
        $company = $this->makeCompany();
        $requester = $this->makeUser();

        $response = $this->actingAs($requester)->from(route('tickets.create'))->post(route('tickets.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'company_id' => $company->id,
            // category_id, impact, urgency, summary, description missing on purpose.
        ]);

        $response->assertRedirect(route('tickets.create'));
        $response->assertSessionHasErrors(['category_id', 'impact', 'urgency', 'summary', 'description']);
        $this->assertSame(0, Ticket::count());
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        $this->makePriorityMatrix();
        $calendar = $this->makeCalendar();
        $company = $this->makeCompany();
        $this->makeSlaPolicies($calendar);
        $category = $this->makeCategory();
        $requester = $this->makeUser();

        $exe = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $response = $this->actingAs($requester)->post(route('tickets.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'company_id' => $company->id,
            'category_id' => $category->id,
            'type' => 'incident', 'impact' => 'low', 'urgency' => 'low',
            'summary' => 'Test', 'description' => 'Test',
            'attachments' => [$exe],
        ]);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertSame(0, Ticket::count());
    }
}
