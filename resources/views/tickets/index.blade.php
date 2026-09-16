<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tickets') }}</h2>
            <a href="{{ route('tickets.export', request()->query()) }}" class="text-sm text-indigo-600 hover:underline">Export CSV</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap gap-2">
                @foreach(['all' => 'All visible', 'mine' => 'My tickets', 'assigned' => 'Assigned to me', 'unassigned' => 'Unassigned'] as $key => $label)
                    <a href="{{ route('tickets.index', array_merge(request()->except('page'), ['view' => $key])) }}"
                       class="px-4 py-2 rounded-full text-sm {{ $view === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Ticket</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Summary</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Project/Site</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Requester</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3"><a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:underline font-medium">{{ $ticket->ticket_number }}</a>
                                    @if($ticket->restricted) <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-700">restricted</span> @endif
                                </td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs font-semibold {{ $ticket->priority === 'P1' ? 'bg-red-100 text-red-700' : ($ticket->priority === 'P2' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-700') }}">{{ $ticket->priority }}</span></td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', $ticket->status) }}</td>
                                <td class="px-4 py-3 max-w-xs truncate">{{ $ticket->summary }}</td>
                                <td class="px-4 py-3">{{ $ticket->project?->name ?? 'HQ' }} @if($ticket->site) / {{ $ticket->site->name }} @endif</td>
                                <td class="px-4 py-3">{{ $ticket->requester?->name }}</td>
                                <td class="px-4 py-3">{{ $ticket->assignedAgent?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No tickets to show.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tickets->links() }}
        </div>
    </div>
</x-app-layout>
