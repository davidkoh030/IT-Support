<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $ticket->ticket_number }} - {{ $ticket->summary }}
                @if($ticket->restricted) <span class="ml-2 text-sm px-2 py-1 rounded bg-red-100 text-red-700">Restricted</span> @endif
            </h2>
            <span class="px-3 py-1 rounded text-sm font-semibold {{ $ticket->priority === 'P1' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700' }}">{{ $ticket->priority }} - {{ str_replace('_', ' ', $ticket->status) }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 text-green-700 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white rounded-lg shadow-sm p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><div class="text-gray-500">Requester</div><div class="font-medium">{{ $ticket->requester->name }}</div></div>
                <div><div class="text-gray-500">Company</div><div class="font-medium">{{ $ticket->company->name }}</div></div>
                <div><div class="text-gray-500">Project</div><div class="font-medium">{{ $ticket->project?->name ?? 'HQ / shared services' }}</div></div>
                <div><div class="text-gray-500">Site</div><div class="font-medium">{{ $ticket->site?->name ?? '-' }}</div></div>
                <div><div class="text-gray-500">Category</div><div class="font-medium">{{ $ticket->category->name }}</div></div>
                <div><div class="text-gray-500">Impact / Urgency</div><div class="font-medium capitalize">{{ $ticket->impact }} / {{ $ticket->urgency }}</div></div>
                <div><div class="text-gray-500">Assigned agent</div><div class="font-medium">{{ $ticket->assignedAgent?->name ?? 'Unassigned' }}</div></div>
                <div><div class="text-gray-500">Approval</div><div class="font-medium capitalize">{{ str_replace('_', ' ', $ticket->approval_state) }}</div></div>
                @if($ticket->deadline_at)
                    <div class="sm:col-span-2"><div class="text-gray-500">Deadline</div><div class="font-medium">{{ $ticket->deadline_at->format('d M Y H:i') }} - {{ $ticket->deadline_reason }}</div></div>
                @endif
                @if($ticket->vendor)
                    <div class="sm:col-span-2"><div class="text-gray-500">Vendor</div><div class="font-medium">{{ $ticket->vendor->name }} (case {{ $ticket->vendor_case_number ?? '-' }})</div></div>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-2">Description</h3>
                <p class="text-gray-700 whitespace-pre-line">{{ $ticket->description }}</p>
                @if($ticket->workaround)
                    <p class="mt-3 text-sm text-gray-500"><strong>Workaround:</strong> {{ $ticket->workaround }}</p>
                @endif
                @if($ticket->resolution_code)
                    <p class="mt-3 text-sm text-green-700"><strong>Resolution ({{ $ticket->resolution_code }}):</strong> {{ $ticket->resolution_notes }}</p>
                @endif
            </div>

            @if($ticket->slaClocks->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">SLA clocks</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        @foreach($ticket->slaClocks as $clock)
                            <div class="border rounded p-3 {{ $clock->isBreached() ? 'border-red-300 bg-red-50' : 'border-gray-200' }}">
                                <div class="font-medium capitalize">{{ str_replace('_', ' ', $clock->metric) }}</div>
                                <div class="text-gray-600">Target: {{ $clock->effectiveTargetAt()->format('d M H:i') }}</div>
                                @if($clock->achieved_at) <div class="text-green-700">Achieved {{ $clock->achieved_at->format('d M H:i') }}</div>
                                @elseif($clock->isPaused()) <div class="text-amber-700">Paused ({{ $clock->pause_reason }})</div>
                                @elseif($clock->isBreached()) <div class="text-red-700 font-semibold">Breached</div>
                                @else <div class="text-gray-500">In progress</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($ticket->checklistTasks->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Checklist</h3>
                    <ul class="space-y-2">
                        @foreach($ticket->checklistTasks as $task)
                            <li class="flex items-center justify-between text-sm border-b last:border-b-0 border-gray-100 pb-2">
                                <div>
                                    <span class="{{ $task->status === 'completed' ? 'line-through text-gray-400' : 'text-gray-800' }}">{{ $task->name }}</span>
                                    <span class="text-xs text-gray-400">({{ $task->assigned_role }})</span>
                                    @if($task->status === 'completed')
                                        <span class="text-xs text-green-600">completed by {{ $task->completedBy?->name }} - {{ $task->evidence_note }}</span>
                                    @endif
                                </div>
                                @if($task->status === 'pending' && $canManage)
                                    <form method="POST" action="{{ route('tickets.checklist.complete', [$ticket, $task]) }}" class="flex gap-2 items-center">
                                        @csrf
                                        <input type="text" name="evidence_note" placeholder="evidence note" class="text-xs rounded border-gray-300">
                                        <button class="text-xs px-2 py-1 bg-gray-800 text-white rounded">Mark done</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($ticket->approvals->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Approvals</h3>
                    @foreach($ticket->approvals as $approval)
                        <div class="flex items-center justify-between text-sm border-b last:border-b-0 border-gray-100 py-2">
                            <div>
                                <span class="capitalize font-medium">{{ str_replace('_', ' ', $approval->approver_role) }}</span>
                                - {{ $approval->approverUser?->name }}
                                <span class="ml-2 px-2 py-0.5 rounded text-xs {{ $approval->decision === 'approved' ? 'bg-green-100 text-green-700' : ($approval->decision === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">{{ $approval->decision }}</span>
                            </div>
                            @if($approval->decision === 'pending' && auth()->id() === $approval->approver_user_id)
                                <form method="POST" action="{{ route('tickets.approvals.decide', [$ticket, $approval]) }}" class="flex gap-2 items-center">
                                    @csrf
                                    <input type="text" name="reason" placeholder="reason (optional)" class="text-xs rounded border-gray-300">
                                    <button name="decision" value="approved" class="text-xs px-2 py-1 bg-green-600 text-white rounded">Approve</button>
                                    <button name="decision" value="rejected" class="text-xs px-2 py-1 bg-red-600 text-white rounded">Reject</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if($canManage)
                <div class="bg-white rounded-lg shadow-sm p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Manage ticket</h3>

                    <form method="POST" action="{{ route('tickets.transition', $ticket) }}" class="flex flex-wrap gap-2 items-end" x-data="{status: ''}">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500">Move to status</label>
                            <select name="status" x-model="status" class="rounded border-gray-300 text-sm">
                                <option value="">Choose...</option>
                                @foreach(\App\Models\Ticket::ALLOWED_TRANSITIONS[$ticket->status] ?? [] as $next)
                                    <option value="{{ $next }}">{{ str_replace('_', ' ', $next) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="status === 'resolved'">
                            <label class="block text-xs text-gray-500">Resolution code</label>
                            <input type="text" name="resolution_code" class="rounded border-gray-300 text-sm">
                        </div>
                        <div x-show="status === 'resolved'" class="flex-1 min-w-[200px]">
                            <label class="block text-xs text-gray-500">Resolution notes</label>
                            <input type="text" name="resolution_notes" class="rounded border-gray-300 text-sm w-full">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Note (optional)</label>
                            <input type="text" name="note" class="rounded border-gray-300 text-sm">
                        </div>
                        <button class="px-3 py-2 bg-indigo-600 text-white text-sm rounded">Update status</button>
                    </form>

                    <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="flex gap-2 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500">Assign to</label>
                            <select name="agent_id" class="rounded border-gray-300 text-sm">
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" @selected($ticket->assigned_agent_id === $agent->id)>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="px-3 py-2 bg-gray-800 text-white text-sm rounded">Assign</button>
                    </form>

                    <form method="POST" action="{{ route('tickets.priority', $ticket) }}" class="flex gap-2 items-end flex-wrap">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500">Override priority</label>
                            <select name="priority" class="rounded border-gray-300 text-sm">
                                @foreach(['P1', 'P2', 'P3', 'P4'] as $p)
                                    <option value="{{ $p }}" @selected($ticket->priority === $p)>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs text-gray-500">Reason (required, audited)</label>
                            <input type="text" name="reason" required class="rounded border-gray-300 text-sm w-full">
                        </div>
                        <button class="px-3 py-2 bg-amber-600 text-white text-sm rounded">Override</button>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Activity</h3>
                <div class="space-y-3">
                    @foreach($comments as $comment)
                        <div class="p-3 rounded {{ $comment->isInternal() ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50' }}">
                            <div class="text-xs text-gray-500 flex justify-between">
                                <span>{{ $comment->user->name }} @if($comment->isInternal()) <span class="text-yellow-700 font-semibold">(internal note)</span> @endif</span>
                                <span>{{ $comment->created_at->format('d M H:i') }}</span>
                            </div>
                            <p class="text-gray-800 mt-1 whitespace-pre-line">{{ $comment->body }}</p>
                            @foreach($comment->attachments as $att)
                                <a href="{{ route('attachments.show', $att) }}" class="text-xs text-indigo-600 hover:underline block mt-1">{{ $att->original_filename }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                    @csrf
                    <textarea name="body" rows="3" required class="block w-full rounded-md border-gray-300" placeholder="Add a reply..."></textarea>
                    <input type="file" name="attachments[]" multiple accept="image/*,.pdf" class="block text-sm">
                    <div class="flex items-center gap-3">
                        <select name="visibility" class="rounded border-gray-300 text-sm">
                            <option value="public">Public reply</option>
                            @can('commentInternal', $ticket)
                                <option value="internal">Internal note (IT only)</option>
                            @endcan
                        </select>
                        <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded">Post</button>
                    </div>
                </form>
            </div>

            @if($canManage)
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-2">Share this ticket</h3>
                    <form method="POST" action="{{ route('tickets.share', $ticket) }}" class="flex gap-2">
                        @csrf
                        <input type="number" name="user_id" placeholder="User ID" class="rounded border-gray-300 text-sm" required>
                        <button class="px-3 py-2 bg-gray-800 text-white text-sm rounded">Share</button>
                    </form>
                </div>
            @endif

            @if(auth()->id() === $ticket->requester_id && in_array($ticket->status, ['resolved', 'closed']))
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-2">How did we do?</h3>
                    <form method="POST" action="{{ route('tickets.satisfaction', $ticket) }}" class="flex gap-2 items-center">
                        @csrf
                        <select name="score" class="rounded border-gray-300 text-sm">
                            @for($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }} / 5</option>@endfor
                        </select>
                        <input type="text" name="comment" placeholder="Optional comment" class="rounded border-gray-300 text-sm flex-1">
                        <button class="px-3 py-2 bg-indigo-600 text-white text-sm rounded">Submit</button>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-3">History</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    @foreach($events as $event)
                        <li>{{ $event->created_at->format('d M H:i') }} - {{ $event->actor?->name ?? 'System' }}: {{ str_replace('_', ' ', $event->event_type) }} {{ $event->from_value ? "({$event->from_value} -> {$event->to_value})" : '' }} {{ $event->note }}</li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
