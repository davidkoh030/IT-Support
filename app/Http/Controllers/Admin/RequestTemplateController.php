<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestTemplateController extends Controller
{
    public function index()
    {
        return view('admin.request-templates.index', [
            'templates' => config('itsm.request_templates'),
            'companies' => Company::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Raises a service request ticket from a configured template
     * (joiner/transfer/leaver/external access/mobilisation/demobilisation),
     * seeding its checklist tasks and approval step(s). The template
     * definition lives in config/itsm.php - there is no GUI editor for the
     * template structure itself in this pilot.
     */
    public function store(Request $request, string $key, TicketService $tickets)
    {
        $template = config("itsm.request_templates.{$key}");
        abort_if($template === null, 404, 'Unknown request template.');

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'subject_user_id' => ['required', 'exists:users,id'],
            'summary' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'approver_user_id' => ['required', 'exists:users,id'],
        ]);

        $category = Category::firstOrCreate(['name' => $template['category']], ['type' => 'service_request']);

        $ticket = $tickets->create($request->user(), [
            'company_id' => $data['company_id'],
            'project_id' => $data['project_id'] ?? null,
            'category_id' => $category->id,
            'type' => 'service_request',
            'impact' => 'medium',
            'urgency' => 'medium',
            'summary' => $data['summary'],
            'description' => $data['description'],
            'approval_required' => true,
        ], (string) Str::uuid());

        foreach ($template['checklist'] as $item) {
            $ticket->checklistTasks()->create([
                'name' => $item['name'],
                'assigned_role' => $item['assigned_role'],
            ]);
        }

        foreach ($template['approvals'] as $role) {
            $approval = Approval::create([
                'approvable_type' => Ticket::class,
                'approvable_id' => $ticket->id,
                'approver_role' => $role === 'manager' ? 'manager' : $role,
                'approver_user_id' => $data['approver_user_id'],
            ]);
            $approval->approverUser->notify(new ApprovalRequested($approval));
        }

        AuditLog::record($request->user(), "request_template.{$key}", $ticket, null, ['subject_user_id' => $data['subject_user_id']]);

        return redirect()->route('tickets.show', $ticket)->with('status', 'Request raised.');
    }
}
