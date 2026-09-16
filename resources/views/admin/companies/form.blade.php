<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $company->exists ? 'Edit' : 'New' }} company</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $company->exists ? route('admin.companies.update', $company) : route('admin.companies.store') }}" class="space-y-4">
                @csrf
                @if($company->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $company->name)" required /></div>
                <div><x-input-label value="Code" /><x-text-input name="code" class="mt-1 block w-full" :value="old('code', $company->code)" required /></div>
                <div>
                    <x-input-label value="Parent company (group)" />
                    <select name="parent_company_id" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">None (this is the group/top-level entity)</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" @selected(old('parent_company_id', $company->parent_company_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><x-input-label value="Country code" /><x-text-input name="country_code" class="mt-1 block w-full" :value="old('country_code', $company->country_code ?? 'SG')" required /></div>
                    <div><x-input-label value="Timezone" /><x-text-input name="timezone" class="mt-1 block w-full" :value="old('timezone', $company->timezone ?? 'Asia/Singapore')" required /></div>
                    <div><x-input-label value="Currency" /><x-text-input name="currency" class="mt-1 block w-full" :value="old('currency', $company->currency ?? 'SGD')" required /></div>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
