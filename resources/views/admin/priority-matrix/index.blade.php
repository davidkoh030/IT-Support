<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        @if(session('status'))<div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('status') }}</div>@endif
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Priority matrix (impact x urgency -> priority)</h3>
            <form method="POST" action="{{ route('admin.priority-matrix.update') }}"
                @csrf @method('PUT')
                <table class="min-w-full text-sm border-collapse">
                    <thead>
                        <tr>
                            <th class="p-2 text-left">Impact \ Urgency</th>
                            <th class="p-2">High</th><th class="p-2">Medium</th><th class="p-2">Low</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['high', 'medium', 'low'] as $impact)
                            <tr>
                                <td class="p-2 font-medium capitalize">{{ $impact }}</td>
                                @foreach(['high', 'medium', 'low'] as $urgency)
                                    @php $key = "{$impact}:{$urgency}"; $current = $rules[$key]->priority ?? 'P4'; @endphp
                                    <td class="p-2 text-center">
                                        <select name="rules[{{ $key }}]" class="rounded border-gray-300 text-sm">
                                            @foreach(['P1','P2','P3','P4'] as $p)
                                                <option value="{{ $p }}" @selected($current === $p)>{{ $p }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded">Save matrix</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
