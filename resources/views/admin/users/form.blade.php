<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Admin</h2></x-slot>
    <div class="py-8"><div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @include('admin._nav')
        @if($errors->any())<div class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">{{ $errors->first() }}</div>@endif

        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">{{ $user->exists ? 'Edit user' : 'Invite user' }}</h3>
            <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-4">
                @csrf
                @if($user->exists) @method('PUT') @endif
                <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required /></div>
                <div><x-input-label value="Email" /><x-text-input type="email" name="email" class="mt-1 block w-full" :value="old('email', $user->email)" required /></div>
                <div><x-input-label value="Phone" /><x-text-input name="phone" class="mt-1 block w-full" :value="old('phone', $user->phone)" /></div>
                <div>
                    <x-input-label value="Vendor (only for external vendor contacts)" />
                    <select name="vendor_id" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">-</option>
                        @foreach($vendors as $v)<option value="{{ $v->id }}" @selected(old('vendor_id', $user->vendor_id) == $v->id)>{{ $v->name }}</option>@endforeach
                    </select>
                </div>
                @if($user->exists)
                    <label class="flex items-center text-sm"><input type="checkbox" name="is_active" value="1" class="mr-2" @checked(old('is_active', $user->is_active))> Active</label>
                    <label class="flex items-center text-sm"><input type="checkbox" name="is_administrator" value="1" class="mr-2" @checked($user->hasRole('administrator'))> System administrator (unscoped, full access)</label>
                @else
                    <p class="text-sm text-gray-500">A password-reset link will be queued (log-driver mail in local dev) so the invitee sets their own password. No self-registration.</p>
                @endif
                <button class="px-4 py-2 bg-indigo-600 text-white rounded">{{ $user->exists ? 'Save' : 'Send invite' }}</button>
            </form>
        </div>

        @if($user->exists)
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Access grants</h3>
                <table class="min-w-full text-sm divide-y divide-gray-100 mb-4">
                    <thead><tr class="text-left text-gray-500"><th class="py-2">Role</th><th>Scope</th><th>Effective</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($user->accessGrants as $grant)
                            <tr>
                                <td class="py-2 capitalize">{{ str_replace('_',' ',$grant->role) }}</td>
                                <td>{{ $grant->scope_type }} #{{ $grant->scope_id }}</td>
                                <td>{{ $grant->effective_date->toDateString() }}</td>
                                <td>{{ $grant->expiry_date?->toDateString() ?? 'None' }}</td>
                                <td>{{ $grant->revoked_at ? 'Revoked' : ($grant->isActive() ? 'Active' : 'Inactive') }}</td>
                                <td>
                                    @if(!$grant->revoked_at)
                                        <form method="POST" action="{{ route('admin.access-grants.revoke', $grant) }}" onsubmit="return confirm('Revoke this access grant?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <form method="POST" action="{{ route('admin.users.access-grants.store', $user) }}" class="grid grid-cols-2 sm:grid-cols-3 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">Role</label>
                        <select name="role" class="rounded border-gray-300 text-sm w-full">
                            @foreach(['member','project_manager','it_agent','it_manager','approver','auditor'] as $r)
                                <option value="{{ $r }}">{{ str_replace('_',' ',$r) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Scope type</label>
                        <select name="scope_type" class="rounded border-gray-300 text-sm w-full">
                            <option value="company">Company</option>
                            <option value="project">Project</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Scope</label>
                        <select name="scope_id" class="rounded border-gray-300 text-sm w-full">
                            <optgroup label="Companies">
                                @foreach($companies ?? [] as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Effective date</label>
                        <input type="date" name="effective_date" value="{{ now()->toDateString() }}" class="rounded border-gray-300 text-sm w-full" required>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Expiry date (optional)</label>
                        <input type="date" name="expiry_date" class="rounded border-gray-300 text-sm w-full">
                    </div>
                    <button class="px-3 py-2 bg-gray-800 text-white text-sm rounded">Add grant</button>
                </form>
                <p class="text-xs text-gray-400 mt-2">Note: the scope dropdown lists companies; to grant a project-scoped role, use the project's id as the Scope value with Scope type = Project (see Admin &gt; Projects for ids).</p>
            </div>
        @endif
    </div></div>
</x-app-layout>
