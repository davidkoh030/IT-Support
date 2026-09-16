<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $project->exists ? 'Edit' : 'New' }} project</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}" class="space-y-4">
                @csrf
                @if($project->exists) @method('PUT') @endif
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Code" /><x-text-input name="code" class="mt-1 block w-full" :value="old('code', $project->code)" required /></div>
                    <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $project->name)" required /></div>
                </div>
                <div>
                    <x-input-label value="Company" />
                    <select name="company_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id', $project->company_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Lifecycle stage" />
                    <select name="lifecycle_stage" class="mt-1 block w-full rounded-md border-gray-300">
                        @foreach(['proposed', 'mobilising', 'active', 'demobilising', 'closed'] as $stage)
                            <option value="{{ $stage }}" @selected(old('lifecycle_stage', $project->lifecycle_stage ?? 'proposed') === $stage)>{{ ucfirst($stage) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Start date" /><input type="date" name="start_date" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('start_date', $project->start_date?->toDateString()) }}"></div>
                    <div><x-input-label value="End date" /><input type="date" name="end_date" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('end_date', $project->end_date?->toDateString()) }}"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Project manager" />
                        <select name="manager_user_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">-</option>
                            @foreach($managers as $u)<option value="{{ $u->id }}" @selected(old('manager_user_id', $project->manager_user_id) == $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Information manager" />
                        <select name="information_manager_user_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">-</option>
                            @foreach($managers as $u)<option value="{{ $u->id }}" @selected(old('information_manager_user_id', $project->information_manager_user_id) == $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Timezone" /><x-text-input name="timezone" class="mt-1 block w-full" :value="old('timezone', $project->timezone ?? 'Asia/Singapore')" required /></div>
                    <div><x-input-label value="Currency" /><x-text-input name="currency" class="mt-1 block w-full" :value="old('currency', $project->currency ?? 'SGD')" required /></div>
                </div>
                <div>
                    <x-input-label value="Sites" />
                    <div class="mt-1 space-y-1">
                        @foreach($sites as $site)
                            <label class="flex items-center text-sm"><input type="checkbox" name="site_ids[]" value="{{ $site->id }}" class="mr-2" @checked(in_array($site->id, old('site_ids', $selectedSiteIds)))> {{ $site->name }}</label>
                        @endforeach
                    </div>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
