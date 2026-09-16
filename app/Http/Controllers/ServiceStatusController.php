<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class ServiceStatusController extends Controller
{
    /**
     * Publishes approved service-interruption status without exposing
     * private ticket content: only major incidents (tickets other reports
     * link to) and their public summary/status are shown, scoped to
     * companies/projects the viewer is authorised for.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $majorIncidents = Ticket::query()
            ->visibleTo($user)
            ->whereHas('linkedReports')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->with(['company', 'project', 'site', 'linkedReports'])
            ->latest()
            ->get();

        return view('service-status.index', compact('majorIncidents'));
    }
}
