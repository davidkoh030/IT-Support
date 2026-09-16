<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketSlaClock;
use Illuminate\Support\Collection;

/**
 * Owns the two SLA clocks (first_response, restoration) per ticket.
 * Targets are computed once against the SLA policy's business-hours
 * calendar and frozen into ticket_sla_clocks.target_at; a pause only ever
 * pushes achievement further out via paused_seconds, it never rewrites the
 * original target, so history stays auditable.
 */
class SlaClockService
{
    public function startClocks(Ticket $ticket): void
    {
        if ($ticket->slaPolicy === null) {
            return;
        }

        $calendar = $ticket->slaPolicy->calendar;
        $created = $ticket->created_at ?? now();

        $ticket->slaClocks()->create([
            'metric' => 'first_response',
            'target_at' => $calendar->addBusinessMinutes($created, $ticket->slaPolicy->first_response_minutes),
        ]);

        $ticket->slaClocks()->create([
            'metric' => 'restoration',
            'target_at' => $calendar->addBusinessMinutes($created, $ticket->slaPolicy->restoration_minutes),
        ]);
    }

    public function recordFirstResponse(Ticket $ticket): void
    {
        if ($ticket->first_response_at !== null) {
            return;
        }

        $ticket->update(['first_response_at' => now()]);

        $clock = $ticket->slaClocks()->where('metric', 'first_response')->first();
        $clock?->update(['achieved_at' => now()]);
    }

    public function recordRestoration(Ticket $ticket): void
    {
        if ($ticket->restoration_at !== null) {
            return;
        }

        $ticket->update(['restoration_at' => now()]);

        $clock = $ticket->slaClocks()->where('metric', 'restoration')->first();
        $clock?->update(['achieved_at' => now()]);
    }

    /**
     * Only "waiting for requester" pauses the restoration clock by default
     * (vendor-waiting keeps counting) - matches section 7's default policy.
     */
    public function pauseRestoration(Ticket $ticket, string $reason): void
    {
        $clock = $ticket->slaClocks()->where('metric', 'restoration')->first();

        if ($clock === null || $clock->isPaused()) {
            return;
        }

        $clock->update(['paused_at' => now(), 'pause_reason' => $reason]);
    }

    public function resumeRestoration(Ticket $ticket): void
    {
        $clock = $ticket->slaClocks()->where('metric', 'restoration')->first();

        if ($clock === null || ! $clock->isPaused()) {
            return;
        }

        $pausedSeconds = $clock->paused_at->diffInSeconds(now());

        $clock->update([
            'paused_seconds' => $clock->paused_seconds + $pausedSeconds,
            'paused_at' => null,
            'pause_reason' => null,
        ]);
    }

    /**
     * Re-derive both clocks' targets after a priority change or policy
     * change, preserving elapsed paused time and any already-achieved
     * timestamp so earlier breaches are not erased.
     */
    public function rebaseAfterPolicyChange(Ticket $ticket): void
    {
        if ($ticket->slaPolicy === null) {
            return;
        }

        $calendar = $ticket->slaPolicy->calendar;
        $created = $ticket->created_at;

        foreach (['first_response' => $ticket->slaPolicy->first_response_minutes, 'restoration' => $ticket->slaPolicy->restoration_minutes] as $metric => $minutes) {
            $clock = $ticket->slaClocks()->where('metric', $metric)->first();

            if ($clock === null || $clock->achieved_at !== null) {
                continue;
            }

            $clock->update(['target_at' => $calendar->addBusinessMinutes($created, $minutes)]);
        }
    }

    /**
     * Called by the scheduled sla:scan command. Sends (and dedupes via the
     * warned_at/breached_notified_at columns) pre-breach and breach
     * notifications. Re-running the worker must not duplicate messages.
     */
    public function dueClocksNeedingWarning(int $warnMinutesBefore = 30): Collection
    {
        return TicketSlaClock::query()
            ->whereNull('achieved_at')
            ->whereNull('warned_at')
            ->whereNull('paused_at')
            ->get()
            ->filter(function (TicketSlaClock $clock) use ($warnMinutesBefore) {
                $deadline = $clock->effectiveTargetAt();

                return now()->addMinutes($warnMinutesBefore)->gte($deadline) && now()->lt($deadline);
            });
    }

    public function dueClocksBreached(): Collection
    {
        return TicketSlaClock::query()
            ->whereNull('achieved_at')
            ->whereNull('breached_notified_at')
            ->whereNull('paused_at')
            ->get()
            ->filter(fn (TicketSlaClock $clock) => $clock->isBreached());
    }
}
