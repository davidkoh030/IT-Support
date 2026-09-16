<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @include('admin._nav')
        @if(session('status'))<div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif

        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-2">Access &amp; lifecycle request templates</h3>
            <p class="text-sm text-gray-500 mb-4">Template checklist/approval definitions live in <code>config/itsm.php</code> (code-level configurable; there is no GUI template editor in this pilot). Raising a request here creates a service-request ticket with the checklist and approval step already attached.</p>

            @foreach($templates as $key => $template)
                <details class="border-t border-gray-100 py-3">
                    <summary class="cursor-pointer font-medium text-gray-800">{{ ucwords(str_replace('_', ' ', $key)) }} <span class="text-xs text-gray-400">({{ count($template['checklist']) }} checklist tasks, approver: {{ implode(', ', $template['approvals']) }})</span></summary>
                    <form method="POST" action="{{ route('admin.request-templates.store', $key) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500">Company</label>
                            <select name="company_id" class="rounded border-gray-300 text-sm w-full" required>
                                @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Project (optional)</label>
                            <select name="project_id" class="rounded border-gray-300 text-sm w-full">
                                <option value="">HQ / shared services</option>
                                @foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Subject user (the person this is about)</label>
                            <select name="subject_user_id" class="rounded border-gray-300 text-sm w-full" required>
                                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Approver</label>
                            <select name="approver_user_id" class="rounded border-gray-300 text-sm w-full" required>
                                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-500">Summary</label>
                            <input type="text" name="summary" class="rounded border-gray-300 text-sm w-full" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-500">Description</label>
                            <textarea name="description" rows="2" class="rounded border-gray-300 text-sm w-full" required></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <button class="px-4 py-2 bg-indigo-600 text-white text-sm rounded">Raise request</button>
                        </div>
                    </form>
                </details>
            @endforeach
        </div>
    </div></div>
</x-app-layout>
