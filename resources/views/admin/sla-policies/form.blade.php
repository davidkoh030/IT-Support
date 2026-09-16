<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $policy->exists ? 'Edit' : 'New' }} SLA policy</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $policy->exists ? route('admin.sla-policies.update', $policy) : route('admin.sla-policies.store') }}" class="space-y-4">
                @csrf
                @if($policy->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $policy->name)" required /></div>
                <div>
                    <x-input-label value="Priority" />
                    <select name="priority" class="mt-1 block w-full rounded-md border-gray-300">
                        @foreach(['P1','P2','P3','P4'] as $p)<option value="{{ $p }}" @selected(old('priority', $policy->priority ?? '') === $p)>{{ $p }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="First response (minutes)" /><x-text-input type="number" name="first_response_minutes" class="mt-1 block w-full" :value="old('first_response_minutes', $policy->first_response_minutes)" required /></div>
                    <div><x-input-label value="Restoration (minutes)" /><x-text-input type="number" name="restoration_minutes" class="mt-1 block w-full" :value="old('restoration_minutes', $policy->restoration_minutes)" required /></div>
                </div>
                <div>
                    <x-input-label value="Calendar" />
                    <select name="calendar_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach($calendars as $cal)<option value="{{ $cal->id }}" @selected(old('calendar_id', $policy->calendar_id) == $cal->id)>{{ $cal->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Company scope (optional - blank applies as the default for this priority)" />
                    <select name="company_id" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">Default (all companies)</option>
                        @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id', $policy->company_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <label class="flex items-center text-sm"><input type="checkbox" name="is_active" value="1" class="mr-2" @checked(old('is_active', $policy->is_active ?? true))> Active</label>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
