<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $site->exists ? 'Edit' : 'New' }} site</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $site->exists ? route('admin.sites.update', $site) : route('admin.sites.store') }}" class="space-y-4">
                @csrf
                @if($site->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $site->name)" required /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Company" />
                        <select name="company_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                            @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id', $site->company_id) == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Type" />
                        <select name="type" class="mt-1 block w-full rounded-md border-gray-300">
                            @foreach(['hq' => 'HQ', 'site_office' => 'Site office', 'warehouse' => 'Warehouse', 'precast_plant' => 'Precast plant'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('type', $site->type ?? 'site_office') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div><x-input-label value="Address" /><x-text-input name="address" class="mt-1 block w-full" :value="old('address', $site->address)" /></div>
                <div><x-input-label value="Area / block / floor / zone" /><x-text-input name="area_block_floor_zone" class="mt-1 block w-full" :value="old('area_block_floor_zone', $site->area_block_floor_zone)" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Operating hours start" /><input type="time" name="operating_hours_start" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('operating_hours_start', $site->operating_hours_start) }}"></div>
                    <div><x-input-label value="Operating hours end" /><input type="time" name="operating_hours_end" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('operating_hours_end', $site->operating_hours_end) }}"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Access contact name" /><x-text-input name="access_contact_name" class="mt-1 block w-full" :value="old('access_contact_name', $site->access_contact_name)" /></div>
                    <div><x-input-label value="Access contact phone" /><x-text-input name="access_contact_phone" class="mt-1 block w-full" :value="old('access_contact_phone', $site->access_contact_phone)" /></div>
                </div>
                <div>
                    <x-input-label value="Support calendar" />
                    <select name="support_calendar_id" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">-</option>
                        @foreach($calendars as $cal)<option value="{{ $cal->id }}" @selected(old('support_calendar_id', $site->support_calendar_id) == $cal->id)>{{ $cal->name }}</option>@endforeach
                    </select>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
