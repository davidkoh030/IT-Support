<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\TicketSlaClock;
use App\Notifications\SlaBreachWarning;
use App\Services\SlaClockService;
use Illuminate\Console\Command;

/**
 * Scans open SLA clocks for pre-breach warnings and breaches, notifying the
 * assigned agent (or the ticket's IT manager if unassigned) exactly once per
 * clock per threshold - warned_at/breached_notified_at make this safe to
 * re-run on every scheduler tick without duplicate messages.
 */
class SlaScan extends Command
{
    protected $signature = 'sla:scan';

    protected $description = 'Send pre-breach and breach SLA notifications, deduplicated per clock.';

    public function handle(SlaClockService $slaClocks): int
    {
        $warned = 0;
        foreach ($slaClocks->dueClocksNeedingWarning() as $clock) {
            $this->notifyFor($clock, false);
            $clock->update(['warned_at' => now()]);
            $warned++;
        }

        $breached = 0;
        foreach ($slaClocks->dueClocksBreached() as $clock) {
            $this->notifyFor($clock, true);
            $clock->update(['breached_notified_at' => now()]);
            $breached++;
        }

        $this->info("SLA scan complete: {$warned} warning(s), {$breached} breach notification(s).");

        return self::SUCCESS;
    }

    private function notifyFor(TicketSlaClock $clock, bool $breached): void
    {
        $ticket = $clock->ticket;
        $recipient = $ticket->assignedAgent;

        if ($recipient === null) {
            return;
        }

        $recipient->notify(new SlaBreachWarning($clock, $breached));
        AuditLog::record(null, $breached ? 'sla.breached' : 'sla.warning', $ticket, null, ['metric' => $clock->metric]);
    }
}
