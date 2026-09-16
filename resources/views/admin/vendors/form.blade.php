<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $vendor->exists ? 'Edit' : 'New' }} vendor</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $vendor->exists ? route('admin.vendors.update', $vendor) : route('admin.vendors.store') }}" class="space-y-4">
                @csrf
                @if($vendor->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $vendor->name)" required /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Contact name" /><x-text-input name="contact_name" class="mt-1 block w-full" :value="old('contact_name', $vendor->contact_name)" /></div>
                    <div><x-input-label value="Contact email" /><x-text-input name="contact_email" class="mt-1 block w-full" :value="old('contact_email', $vendor->contact_email)" /></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Contact phone" /><x-text-input name="contact_phone" class="mt-1 block w-full" :value="old('contact_phone', $vendor->contact_phone)" /></div>
                    <div><x-input-label value="Support hours" /><x-text-input name="support_hours" class="mt-1 block w-full" :value="old('support_hours', $vendor->support_hours)" /></div>
                </div>
                <div><x-input-label value="Warranty reference" /><x-text-input name="warranty_reference" class="mt-1 block w-full" :value="old('warranty_reference', $vendor->warranty_reference)" /></div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>
        </div>
    </div></div>
</x-app-layout>
