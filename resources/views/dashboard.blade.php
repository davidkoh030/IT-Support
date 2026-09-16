<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('tickets.index', ['view' => 'mine']) }}" class="bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition">
                    <div class="text-sm text-gray-500">My open tickets</div>
                    <div class="text-3xl font-bold text-gray-800">{{ $stats['my_open'] }}</div>
                </a>
                <a href="{{ route('tickets.index', ['view' => 'assigned']) }}" class="bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition">
                    <div class="text-sm text-gray-500">Assigned to me</div>
                    <div class="text-3xl font-bold text-gray-800">{{ $stats['assigned_to_me'] }}</div>
                </a>
                @if($stats['unassigned'] !== null)
                    <a href="{{ route('tickets.index', ['view' => 'unassigned']) }}" class="bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition">
                        <div class="text-sm text-gray-500">Unassigned (team)</div>
                        <div class="text-3xl font-bold text-amber-600">{{ $stats['unassigned'] }}</div>
                    </a>
                @endif
                @if($stats['pending_approvals'] !== null)
                    <div class="bg-white rounded-lg shadow-sm p-5">
                        <div class="text-sm text-gray-500">Pending my approval</div>
                        <div class="text-3xl font-bold text-indigo-600">{{ $stats['pending_approvals'] }}</div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow-sm p-5">
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-4 bg-indigo-600 text-white text-lg font-semibold rounded-lg hover:bg-indigo-700 transition">
                    + Report an IT problem or request
                </a>
            </div>

            @if($atRisk->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">At-risk tickets (SLA due within 2 hours)</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach($atRisk as $ticket)
                            <li class="py-2">
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:underline font-medium">{{ $ticket->ticket_number }}</a>
                                <span class="text-gray-600">- {{ $ticket->summary }}</span>
                                <span class="ml-2 inline-block px-2 py-0.5 text-xs rounded bg-red-100 text-red-700">{{ $ticket->priority }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($overdueExternalRevocations->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 border-amber-500">
                    <h3 class="font-semibold text-gray-800 mb-3">Overdue external access revocations</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach($overdueExternalRevocations as $grant)
                            <li class="py-2">
                                <a href="{{ route('tickets.show', $grant->ticket) }}" class="text-indigo-600 hover:underline font-medium">{{ $grant->ticket->ticket_number }}</a>
                                <span class="text-gray-600">- {{ $grant->external_party_name }} expired {{ $grant->expiry_date->diffForHumans() }}, not yet revoked</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-3">My recent tickets</h3>
                @forelse($myTickets as $ticket)
                    <div class="py-2 border-b last:border-b-0 border-gray-100 flex justify-between items-center">
                        <div>
                            <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:underline font-medium">{{ $ticket->ticket_number }}</a>
                            <span class="text-gray-600">- {{ $ticket->summary }}</span>
                        </div>
                        <span class="text-xs uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $ticket->status) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500">No open tickets. <a href="{{ route('tickets.create') }}" class="text-indigo-600 hover:underline">Report one</a>.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
