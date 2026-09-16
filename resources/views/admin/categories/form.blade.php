<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $category->exists ? 'Edit' : 'New' }} category</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-4">
                @csrf
                @if($category->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $category->name)" required /></div>
                <div>
                    <x-input-label value="Type" />
                    <select name="type" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="incident" @selected(old('type', $category->type ?? 'incident') === 'incident')>Incident</option>
                        <option value="service_request" @selected(old('type', $category->type ?? '') === 'service_request')>Service request</option>
                    </select>
                </div>
                <div><x-input-label value="Description" /><x-text-input name="description" class="mt-1 block w-full" :value="old('description', $category->description)" /></div>
                <label class="flex items-center text-sm"><input type="checkbox" name="is_active" value="1" class="mr-2" @checked(old('is_active', $category->is_active ?? true))> Active</label>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
