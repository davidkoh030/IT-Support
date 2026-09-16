<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $calendar->exists ? 'Edit' : 'New' }} calendar</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $calendar->exists ? route('admin.calendars.update', $calendar) : route('admin.calendars.store') }}" class="space-y-4">
                @csrf
                @if($calendar->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $calendar->name)" required /></div>
                <div><x-input-label value="Timezone (IANA name)" /><x-text-input name="timezone" class="mt-1 block w-full" :value="old('timezone', $calendar->timezone ?? 'Asia/Singapore')" required /></div>
                <div>
                    <x-input-label value="Working days" />
                    <div class="flex gap-3 mt-1">
                        @php $days = old('working_days', $calendar->working_days ?? [1,2,3,4,5]); @endphp
                        @foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $num => $label)
                            <label class="text-sm"><input type="checkbox" name="working_days[]" value="{{ $num }}" @checked(in_array($num, $days))> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Start time" /><input type="time" name="start_time" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('start_time', $calendar->start_time ?? '08:30') }}" required></div>
                    <div><x-input-label value="End time" /><input type="time" name="end_time" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('end_time', $calendar->end_time ?? '17:30') }}" required></div>
                </div>
                <div>
                    <x-input-label value="Holidays" />
                    <div id="holidays" class="space-y-2 mt-1">
                        @forelse($calendar->holidays ?? [] as $holiday)
                            <div class="flex gap-2"><input type="date" name="holiday_dates[]" value="{{ $holiday->date->toDateString() }}" class="rounded border-gray-300 text-sm"><input type="text" name="holiday_names[]" value="{{ $holiday->name }}" class="rounded border-gray-300 text-sm flex-1"></div>
                        @empty
                        @endforelse
                        <div class="flex gap-2"><input type="date" name="holiday_dates[]" class="rounded border-gray-300 text-sm"><input type="text" name="holiday_names[]" placeholder="Holiday name" class="rounded border-gray-300 text-sm flex-1"></div>
                        <div class="flex gap-2"><input type="date" name="holiday_dates[]" class="rounded border-gray-300 text-sm"><input type="text" name="holiday_names[]" placeholder="Holiday name" class="rounded border-gray-300 text-sm flex-1"></div>
                    </div>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
