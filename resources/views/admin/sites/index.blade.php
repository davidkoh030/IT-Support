<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        @if(session('status'))<div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('status') }}</div>@endif
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Sites</h3>
                <a href="{{ route('admin.sites.create') }}" class="text-sm px-3 py-1.5 bg-indigo-600 text-white rounded">+ New site</a>
            </div>
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead><tr class="text-left text-gray-500"><th class="py-2">Name</th><th>Type</th><th>Company</th><th>Calendar</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sites as $site)
                        <tr>
                            <td class="py-2">{{ $site->name }}</td>
                            <td class="capitalize">{{ str_replace('_', ' ', $site->type) }}</td>
                            <td>{{ $site->company->name }}</td>
                            <td>{{ $site->supportCalendar?->name ?? '-' }}</td>
                            <td><a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
</x-app-layout>
