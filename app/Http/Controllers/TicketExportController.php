<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketExportController extends Controller
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $tickets = Ticket::query()->visibleTo($user)->with(['requester', 'category', 'project', 'site'])->get();

        AuditLog::record($user, 'tickets.exported', $tickets->first() ?? new Ticket, null, ['count' => $tickets->count()]);

        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ticket', 'Status', 'Priority', 'Category', 'Company', 'Project', 'Site', 'Requester', 'Summary', 'Created At']);

            foreach ($tickets as $ticket) {
                fputcsv($out, [
                    $ticket->ticket_number,
                    $ticket->status,
                    $ticket->priority,
                    $ticket->category?->name,
                    $ticket->company?->name,
                    $ticket->project?->name,
                    $ticket->site?->name,
                    $ticket->requester?->name,
                    $this->sanitizeCell($ticket->summary),
                    $ticket->created_at?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, 'tickets-export.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Spreadsheet formula-injection protection: a leading =, +, -, @, tab or
     * CR is neutralised with a leading apostrophe so Excel/Sheets renders it
     * as text rather than evaluating it as a formula.
     */
    private function sanitizeCell(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return in_array($value[0], self::DANGEROUS_PREFIXES, true) ? "'".$value : $value;
    }
}
