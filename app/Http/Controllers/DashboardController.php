<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\ExternalAccessGrant;
use App\Models\Ticket;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $base = Ticket::query()->visibleTo($user);

        $isItStaff = $user->hasRole('administrator')
            || $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->exists();
        $isManagerOrAuditor = $user->activeAccessGrants()->whereIn('role', ['project_manager', 'auditor'])->exists();

        $myTickets = (clone $base)->where('requester_id', $user->id)->whereNotIn('status', ['closed', 'cancelled'])->latest()->limit(5)->get();

        $stats = [
            'my_open' => (clone $base)->where('requester_id', $user->id)->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'assigned_to_me' => (clone $base)->where('assigned_agent_id', $user->id)->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'unassigned' => $isItStaff ? (clone $base)->whereNull('assigned_agent_id')->whereNotIn('status', ['closed', 'cancelled'])->count() : null,
            'pending_approvals' => $isItStaff || $isManagerOrAuditor
                ? Approval::query()->where('approver_user_id', $user->id)->where('decision', 'pending')->count()
                : null,
        ];

        $overdueExternalRevocations = $isItStaff
            ? ExternalAccessGrant::query()->whereNull('revoked_at')->where('expiry_date', '<', now()->toDateString())->with('ticket')->get()
            : collect();

        $atRisk = $isItStaff
            ? Ticket::query()->visibleTo($user)->whereNotIn('status', ['resolved', 'closed', 'cancelled'])
                ->whereHas('slaClocks', fn ($q) => $q->whereNull('achieved_at')->where('target_at', '<', now()->addHours(2)))
                ->with('slaClocks')->limit(10)->get()
            : collect();

        return view('dashboard', compact('myTickets', 'stats', 'isItStaff', 'isManagerOrAuditor', 'overdueExternalRevocations', 'atRisk'));
    }
}
