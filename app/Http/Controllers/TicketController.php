<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ChecklistTask;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::query()->visibleTo($user)->with(['requester', 'category', 'project', 'site', 'assignedAgent']);

        $view = $request->query('view', 'all');
        if ($view === 'mine') {
            $query->where('requester_id', $user->id);
        } elseif ($view === 'assigned') {
            $query->where('assigned_agent_id', $user->id);
        } elseif ($view === 'unassigned') {
            $query->whereNull('assigned_agent_id')->where('status', '!=', 'cancelled');
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $tickets = $query->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 ELSE 4 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('tickets.index', compact('tickets', 'view'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get();
        $companies = $user->authorisedCompanies();
        $projects = $user->authorisedProjects();

        $prefill = [
            'asset_id' => $request->query('asset_id'),
            'company_id' => $request->query('company_id'),
            'site_id' => $request->query('site_id'),
        ];

        return view('tickets.create', compact('categories', 'companies', 'projects', 'prefill'));
    }

    public function createForAsset(Request $request, Asset $asset)
    {
        $user = $request->user();
        $authorised = $user->hasRole('administrator')
            || $user->authorisedCompanies()->pluck('id')->contains($asset->company_id)
            || $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->where('scope_type', 'company')->where('scope_id', $asset->company_id)->exists();

        abort_unless($authorised, 403, 'You are not authorised to report against this asset.');

        return redirect()->route('tickets.create', [
            'asset_id' => $asset->id,
            'company_id' => $asset->company_id,
            'site_id' => $asset->site_id,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();

        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:64'],
            'company_id' => ['required', 'exists:companies,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', 'in:incident,service_request'],
            'impact' => ['required', 'in:high,medium,low'],
            'urgency' => ['required', 'in:high,medium,low'],
            'summary' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'affected_users_note' => ['nullable', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'deadline_reason' => ['nullable', 'string', 'max:255'],
            'security_flag' => ['nullable', 'boolean'],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['exists:assets,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ]);

        $ticket = $this->tickets->create($user, [
            ...$data,
            'restricted' => (bool) ($data['security_flag'] ?? false),
        ], $data['idempotency_key']);

        if ($request->filled('asset_ids')) {
            $ticket->assets()->syncWithoutDetaching($data['asset_ids']);
        }

        $this->storeAttachments($request, $ticket, null, $user);

        return redirect()->route('tickets.show', $ticket)->with('status', "Ticket {$ticket->ticket_number} submitted.");
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $user = $request->user();

        $ticket->load(['requester', 'category', 'project', 'site', 'assignedAgent', 'vendor', 'assets', 'attachments', 'slaClocks', 'checklistTasks.completedBy', 'approvals.approverUser']);

        $comments = $ticket->comments()->with('user', 'attachments')->get()
            ->filter(fn ($c) => $c->visibility === 'public' || $request->user()->can('viewInternalNotes', $ticket));

        $events = $ticket->events()->with('actor')->get();
        $canManage = $request->user()->can('transition', $ticket);
        $agents = $canManage ? $this->itStaffFor($ticket) : collect();

        return view('tickets.show', compact('ticket', 'comments', 'events', 'canManage', 'agents'));
    }

    public function storeComment(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'in:public,internal'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ]);

        $ability = $data['visibility'] === 'internal' ? 'commentInternal' : 'comment';
        $this->authorize($ability, $ticket);

        $comment = $this->tickets->addComment($ticket, $request->user(), $data['body'], $data['visibility']);
        $this->storeAttachments($request, $ticket, $comment->id, $request->user());

        return back()->with('status', 'Comment added.');
    }

    public function transition(Request $request, Ticket $ticket)
    {
        $this->authorize('transition', $ticket);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'resolution_code' => ['nullable', 'string', 'max:100'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['status'] === 'resolved') {
            $request->validate([
                'resolution_code' => ['required', 'string', 'max:100'],
                'resolution_notes' => ['required', 'string', 'max:2000'],
            ]);
            $ticket->update(['resolution_code' => $data['resolution_code'], 'resolution_notes' => $data['resolution_notes']]);
        }

        try {
            $this->tickets->transition($ticket, $request->user(), $data['status'], $data['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('status', "Ticket moved to {$data['status']}.");
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $this->authorize('assign', $ticket);

        $data = $request->validate(['agent_id' => ['required', 'exists:users,id']]);
        $agent = User::query()->findOrFail($data['agent_id']);

        $this->tickets->assign($ticket, $request->user(), $agent);

        return back()->with('status', 'Ticket assigned.');
    }

    public function overridePriority(Request $request, Ticket $ticket)
    {
        $this->authorize('overridePriority', $ticket);

        $data = $request->validate([
            'priority' => ['required', 'in:P1,P2,P3,P4'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->tickets->overridePriority($ticket, $request->user(), $data['priority'], $data['reason']);

        return back()->with('status', 'Priority updated.');
    }

    public function share(Request $request, Ticket $ticket)
    {
        $this->authorize('share', $ticket);

        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $ticket->shares()->firstOrCreate(
            ['user_id' => $data['user_id']],
            ['shared_by_id' => $request->user()->id],
        );

        AuditLog::record($request->user(), 'ticket.shared', $ticket, null, ['user_id' => $data['user_id']]);

        return back()->with('status', 'Ticket shared.');
    }

    public function decideApproval(Request $request, Ticket $ticket, Approval $approval)
    {
        abort_unless($approval->approvable_type === Ticket::class && $approval->approvable_id === $ticket->id, 404);
        $this->authorize('decideApproval', $ticket);

        $user = $request->user();
        abort_unless(
            $approval->approver_user_id === $user->id || $approval->delegated_to_id === $user->id,
            403,
            'You are not the assigned approver for this request.'
        );
        abort_if($approval->approver_user_id === $ticket->requester_id, 403, 'Self-approval is not permitted.');

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $approval->update(['decision' => $data['decision'], 'decided_at' => now(), 'reason' => $data['reason'] ?? null]);

        $remaining = $ticket->approvals()->where('decision', 'pending')->count();
        if ($data['decision'] === 'rejected') {
            $ticket->update(['approval_state' => 'rejected']);
        } elseif ($remaining === 0) {
            $ticket->update(['approval_state' => 'approved']);
        }

        AuditLog::record($user, 'approval.decided', $ticket, null, ['approval_id' => $approval->id, 'decision' => $data['decision']]);

        return back()->with('status', 'Approval recorded.');
    }

    public function completeChecklistTask(Request $request, Ticket $ticket, ChecklistTask $task)
    {
        abort_unless($task->ticket_id === $ticket->id, 404);
        $this->authorize('transition', $ticket);

        $data = $request->validate(['evidence_note' => ['nullable', 'string', 'max:255']]);

        $task->update([
            'status' => 'completed',
            'completed_by_id' => $request->user()->id,
            'completed_at' => now(),
            'evidence_note' => $data['evidence_note'] ?? null,
        ]);

        AuditLog::record($request->user(), 'checklist.completed', $ticket, null, ['task_id' => $task->id]);

        return back()->with('status', 'Checklist task completed.');
    }

    public function submitSatisfaction(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->requester_id === $request->user()->id, 403);
        abort_unless(in_array($ticket->status, ['resolved', 'closed'], true), 422, 'Ticket is not resolved yet.');

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->satisfactionScore()->updateOrCreate([], $data);

        return back()->with('status', 'Thank you for your feedback.');
    }

    private function itStaffFor(Ticket $ticket)
    {
        $companyIds = Company::ancestorChain($ticket->company_id);

        $userIds = AccessGrant::query()
            ->whereIn('role', ['it_agent', 'it_manager'])
            ->whereNull('revoked_at')
            ->where(function ($q) use ($ticket, $companyIds) {
                $q->where('scope_type', 'company')->whereIn('scope_id', $companyIds);
                if ($ticket->project_id !== null) {
                    $q->orWhere(['scope_type' => 'project', 'scope_id' => $ticket->project_id]);
                }
            })
            ->pluck('user_id');

        return User::query()->whereIn('id', $userIds)->orderBy('name')->get();
    }

    private function storeAttachments(Request $request, Ticket $ticket, ?int $commentId, User $uploader): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $randomName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('tickets/'.$ticket->id, $randomName, 'private');

            TicketAttachment::query()->create([
                'ticket_id' => $ticket->id,
                'comment_id' => $commentId,
                'uploaded_by_id' => $uploader->id,
                'disk' => 'private',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
