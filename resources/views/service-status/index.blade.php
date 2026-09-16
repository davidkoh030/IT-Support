<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Service status') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                @forelse($majorIncidents as $incident)
                    <div class="border-l-4 border-red-500 bg-red-50 rounded p-4 mb-3">
                        <div class="font-semibold text-gray-800">{{ $incident->summary }}</div>
                        <div class="text-sm text-gray-600">{{ $incident->company->name }} @if($incident->project) - {{ $incident->project->name }} @endif @if($incident->site) - {{ $incident->site->name }} @endif</div>
                        <div class="text-sm text-gray-500 mt-1">Status: {{ str_replace('_', ' ', $incident->status) }} - {{ $incident->linkedReports->count() }} linked report(s)</div>
                    </div>
                @empty
                    <p class="text-gray-500">No active service interruptions reported.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
