<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Deny-by-default authorisation for every ticket operation. This is the
 * single source of truth for "who can see/act on this ticket" - controllers
 * must not duplicate or bypass this logic, and it must not trust anything
 * from the request (route id only).
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // index queries are scoped separately per role; this only gates the route.
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($ticket->requester_id === $user->id) {
            return true;
        }

        if ($ticket->shares()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($user->vendor_id !== null) {
            return $ticket->vendor_id === $user->vendor_id;
        }

        if ($ticket->assigned_agent_id === $user->id) {
            return true;
        }

        if ($this->isItStaffFor($user, $ticket)) {
            return true;
        }

        // Restricted tickets are never visible to ordinary project managers
        // or auditors, even within their normal project/company scope.
        if ($ticket->restricted) {
            return false;
        }

        if ($this->hasScope($user, 'project_manager', $ticket) || $this->hasScope($user, 'auditor', $ticket)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Add a public reply. Internal notes use commentInternal below.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function commentInternal(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('administrator') || $this->isItStaffFor($user, $ticket) || $ticket->assigned_agent_id === $user->id;
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $this->commentInternal($user, $ticket);
    }

    public function transition(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('administrator') || $this->isItStaffFor($user, $ticket) || $ticket->assigned_agent_id === $user->id;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('administrator') || $this->hasScope($user, 'it_manager', $ticket);
    }

    public function overridePriority(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('administrator') || $this->isItStaffFor($user, $ticket);
    }

    /**
     * Approvers must never approve their own request, whatever role they
     * also hold.
     */
    public function decideApproval(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id !== $user->id;
    }

    public function share(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('administrator') || $this->isItStaffFor($user, $ticket) || $ticket->assigned_agent_id === $user->id;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->transition($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false; // tickets are never hard-deleted from the UI; use cancellation.
    }

    private function isItStaffFor(User $user, Ticket $ticket): bool
    {
        return $this->hasScope($user, 'it_agent', $ticket) || $this->hasScope($user, 'it_manager', $ticket);
    }

    private function hasScope(User $user, string $role, Ticket $ticket): bool
    {
        if ($user->hasScopedRole($role, 'company', $ticket->company_id)) {
            return true;
        }

        return $ticket->project_id !== null && $user->hasScopedRole($role, 'project', $ticket->project_id);
    }
}
