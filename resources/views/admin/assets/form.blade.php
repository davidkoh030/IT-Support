<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        @include('admin._nav')
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $asset->exists ? 'Edit' : 'New' }} asset</h3>
            @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ $asset->exists ? route('admin.assets.update', $asset) : route('admin.assets.store') }}" class="space-y-4">
                @csrf
                @if($asset->exists) @method('PUT') @endif
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Tag / QR code" /><x-text-input name="tag" class="mt-1 block w-full" :value="old('tag', $asset->tag)" required /></div>
                    <div><x-input-label value="Type" /><x-text-input name="type" class="mt-1 block w-full" :value="old('type', $asset->type)" required /></div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><x-input-label value="Make" /><x-text-input name="make" class="mt-1 block w-full" :value="old('make', $asset->make)" /></div>
                    <div><x-input-label value="Model" /><x-text-input name="model" class="mt-1 block w-full" :value="old('model', $asset->model)" /></div>
                    <div><x-input-label value="Serial" /><x-text-input name="serial" class="mt-1 block w-full" :value="old('serial', $asset->serial)" /></div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <x-input-label value="Company" />
                        <select name="company_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                            @foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id', $asset->company_id) == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Site" />
                        <select name="site_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">-</option>
                            @foreach($sites as $s)<option value="{{ $s->id }}" @selected(old('site_id', $asset->site_id) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Project" />
                        <select name="project_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">-</option>
                            @foreach($projects as $p)<option value="{{ $p->id }}" @selected(old('project_id', $asset->project_id) == $p->id)>{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Assigned user" />
                        <select name="assigned_user_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">-</option>
                            @foreach($users as $u)<option value="{{ $u->id }}" @selected(old('assigned_user_id', $asset->assigned_user_id) == $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Status" />
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300">
                            @foreach(['in_stock','assigned','loaned','in_repair','retired','disposed'] as $s)
                                <option value="{{ $s }}" @selected(old('status', $asset->status ?? 'in_stock') === $s)>{{ str_replace('_',' ',$s) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Purchase date" /><input type="date" name="purchase_date" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('purchase_date', $asset->purchase_date?->toDateString()) }}"></div>
                    <div><x-input-label value="Warranty expiry" /><input type="date" name="warranty_expiry" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('warranty_expiry', $asset->warranty_expiry?->toDateString()) }}"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><x-input-label value="Supplier" /><x-text-input name="supplier" class="mt-1 block w-full" :value="old('supplier', $asset->supplier)" /></div>
                    <div>
                        <x-input-label value="Vendor" />
                        <select name="vendor_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">-</option>
                            @foreach($vendors as $v)<option value="{{ $v->id }}" @selected(old('vendor_id', $asset->vendor_id) == $v->id)>{{ $v->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><x-input-label value="Approved cost" /><x-text-input type="number" step="0.01" name="approved_cost" class="mt-1 block w-full" :value="old('approved_cost', $asset->approved_cost)" /></div>
                    <div><x-input-label value="Currency" /><x-text-input name="currency" class="mt-1 block w-full" :value="old('currency', $asset->currency)" /></div>
                    <div><x-input-label value="Cost code" /><x-text-input name="cost_code" class="mt-1 block w-full" :value="old('cost_code', $asset->cost_code)" /></div>
                </div>
                <div>
                    <x-input-label value="Network notes (IT-only, never shown to requesters)" />
                    <textarea name="network_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('network_notes', $asset->network_notes) }}</textarea>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
            </form>

            @if($asset->exists && $asset->events->isNotEmpty())
                <div class="mt-6 border-t pt-4">
                    <h4 class="font-medium text-gray-700 mb-2">Custody history</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        @foreach($asset->events as $event)
                            <li>{{ $event->occurred_at->format('d M Y') }} - {{ str_replace('_',' ',$event->event_type) }} by {{ $event->actor?->name }} ({{ $event->fromUser?->name ?? '-' }} -> {{ $event->toUser?->name ?? '-' }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div></div>
</x-app-layout>
