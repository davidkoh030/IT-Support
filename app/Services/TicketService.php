<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketPublicReply;
use App\Notifications\TicketResolved;

class TicketService
{
    public function __construct(
        private readonly PriorityCalculator $priorityCalculator,
        private readonly SlaClockService $slaClocks,
    ) {}

    /**
     * Creates a ticket, or returns the existing one if $idempotencyKey was
     * already used - so a retried submission after a network error never
     * creates a duplicate (acceptance criteria 9).
     */
    public function create(User $requester, array $data, string $idempotencyKey): Ticket
    {
        $existing = Ticket::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $priority = $data['priority_override'] ?? $this->priorityCalculator->calculate($data['impact'], $data['urgency']);
        $overridden = isset($data['priority_override']);

        $slaPolicy = SlaPolicy::query()->where('priority', $priority)->where('is_active', true)->first();

        $ticket = Ticket::query()->create([
            'ticket_number' => $this->nextTicketNumber(),
            'requester_id' => $requester->id,
            'company_id' => $data['company_id'],
            'project_id' => $data['project_id'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'impact' => $data['impact'],
            'urgency' => $data['urgency'],
            'priority' => $priority,
            'priority_overridden' => $overridden,
            'priority_overridden_by_id' => $overridden ? $requester->id : null,
            'priority_override_reason' => $data['priority_override_reason'] ?? null,
            'status' => 'new',
            'approval_state' => $data['approval_required'] ?? false ? 'pending' : 'not_required',
            'restricted' => $data['restricted'] ?? false,
            'summary' => $data['summary'],
            'description' => $data['description'],
            'affected_users_note' => $data['affected_users_note'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
            'deadline_reason' => $data['deadline_reason'] ?? null,
            'sla_policy_id' => $slaPolicy?->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        if ($slaPolicy !== null) {
            $this->slaClocks->startClocks($ticket->fresh());
        }

        $this->logEvent($ticket, $requester, 'created', null, $ticket->status);
        AuditLog::record($requester, 'ticket.created', $ticket, null, $ticket->toArray());

        return $ticket;
    }

    /**
     * @throws \InvalidArgumentException if $toStatus is not a legal next
     *                                   state from the ticket's current status (Ticket::ALLOWED_TRANSITIONS).
     */
    public function transition(Ticket $ticket, User $actor, string $toStatus, ?string $note = null): void
    {
        $from = $ticket->status;

        if ($from === $toStatus) {
            return;
        }

        $allowed = Ticket::ALLOWED_TRANSITIONS[$from] ?? [];
        if (! in_array($toStatus, $allowed, true)) {
            throw new \InvalidArgumentException("Cannot move ticket from {$from} to {$toStatus}.");
        }

        if ($toStatus === 'resolved' && in_array($ticket->approval_state, ['pending', 'rejected'], true)) {
            throw new \InvalidArgumentException("Cannot resolve a ticket whose approval is {$ticket->approval_state}.");
        }

        if ($from === 'waiting_requester' && $toStatus !== 'waiting_requester') {
            $this->slaClocks->resumeRestoration($ticket);
        }

        if ($toStatus === 'waiting_requester') {
            $this->slaClocks->pauseRestoration($ticket, 'waiting_requester');
        }

        if (in_array($toStatus, ['in_progress', 'resolved'], true) && $ticket->restoration_at === null) {
            $this->slaClocks->recordRestoration($ticket);
        }

        if ($toStatus === 'resolved') {
            $ticket->update(['resolved_at' => now()]);
            $ticket->requester->notify(new TicketResolved($ticket));
        }

        if ($toStatus === 'closed') {
            $ticket->update(['closed_at' => now()]);
        }

        if ($from === 'resolved' && $toStatus !== 'closed') {
            // reopen
            $ticket->increment('reopen_count');
        }

        $ticket->update(['status' => $toStatus]);

        $this->logEvent($ticket, $actor, 'status_change', $from, $toStatus, $note);
        AuditLog::record($actor, 'ticket.status_change', $ticket, ['status' => $from], ['status' => $toStatus]);
    }

    public function overridePriority(Ticket $ticket, User $actor, string $priority, string $reason): void
    {
        $from = $ticket->priority;

        $ticket->update([
            'priority' => $priority,
            'priority_overridden' => true,
            'priority_overridden_by_id' => $actor->id,
            'priority_override_reason' => $reason,
        ]);

        $slaPolicy = $this->resolveSlaPolicy($priority, $ticket->company_id);

        if ($slaPolicy !== null) {
            $ticket->update(['sla_policy_id' => $slaPolicy->id]);
            $this->slaClocks->rebaseAfterPolicyChange($ticket->fresh());
        }

        $this->logEvent($ticket, $actor, 'priority_override', $from, $priority, $reason);
        AuditLog::record($actor, 'ticket.priority_override', $ticket, ['priority' => $from], ['priority' => $priority, 'reason' => $reason]);
    }

    public function assign(Ticket $ticket, User $actor, User $agent): void
    {
        $from = $ticket->assigned_agent_id;

        $ticket->update(['assigned_agent_id' => $agent->id, 'status' => $ticket->status === 'new' ? 'assigned' : $ticket->status]);

        $this->logEvent($ticket, $actor, 'assignment', (string) $from, (string) $agent->id);
        AuditLog::record($actor, 'ticket.assigned', $ticket, ['assigned_agent_id' => $from], ['assigned_agent_id' => $agent->id]);
        $agent->notify(new TicketAssigned($ticket));
    }

    public function addComment(Ticket $ticket, User $author, string $body, string $visibility): TicketComment
    {
        $comment = $ticket->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
            'visibility' => $visibility,
        ]);

        if ($visibility === 'public' && $author->id !== $ticket->requester_id) {
            $this->slaClocks->recordFirstResponse($ticket);
            $ticket->requester->notify(new TicketPublicReply($comment));
        }

        AuditLog::record($author, 'ticket.comment', $ticket, null, ['visibility' => $visibility]);

        return $comment;
    }

    private function logEvent(Ticket $ticket, ?User $actor, string $type, ?string $from, ?string $to, ?string $note = null): void
    {
        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor?->id,
            'event_type' => $type,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
        ]);
    }

    /**
     * A company-specific policy (its own calendar, e.g. a Malaysia legal
     * entity) wins over the shared default for the same priority.
     */
    private function resolveSlaPolicy(string $priority, int $companyId): ?SlaPolicy
    {
        return SlaPolicy::query()
            ->where('priority', $priority)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderByRaw('company_id IS NULL')
            ->first();
    }

    private function nextTicketNumber(): string
    {
        $next = (Ticket::query()->max('id') ?? 0) + 1;

        return 'TCK-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
