<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        @if(session('status'))<div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('status') }}</div>@endif
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Assets</h3>
                <a href="{{ route('admin.assets.create') }}" class="text-sm px-3 py-1.5 bg-indigo-600 text-white rounded">+ New asset</a>
            </div>
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead><tr class="text-left text-gray-500"><th class="py-2">Tag</th><th>Type</th><th>Company/Site</th><th>Assigned to</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($assets as $asset)
                        <tr>
                            <td class="py-2">{{ $asset->tag }}</td>
                            <td>{{ $asset->type }} {{ $asset->make }} {{ $asset->model }}</td>
                            <td>{{ $asset->company->name }} / {{ $asset->site?->name ?? '-' }}</td>
                            <td>{{ $asset->assignedUser?->name ?? '-' }}</td>
                            <td class="capitalize">{{ str_replace('_', ' ', $asset->status) }}</td>
                            <td><a href="{{ route('admin.assets.edit', $asset) }}" class="text-indigo-600 hover:underline">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $assets->links() }}
        </div>
    </div></div>
</x-app-layout>
