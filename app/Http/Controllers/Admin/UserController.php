<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::with('accessGrants')->orderBy('name')->paginate(25)]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User, 'vendors' => Vendor::orderBy('name')->get()]);
    }

    /**
     * Invited local account (section 4): no self-registration. A random
     * password is set and a password-reset link is sent immediately so the
     * invitee sets their own; nothing usable is emailed in the clear.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);

        $user = User::create([
            ...$data,
            'password' => Str::random(40),
            'email_verified_at' => now(),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        AuditLog::record($request->user(), 'user.invited', $user, null, ['email' => $user->email]);

        return redirect()->route('admin.users.index')->with('status', "Invited {$user->email}. A password-reset link was queued (see the log-driver mail output in local dev).");
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user->load('accessGrants'),
            'vendors' => Vendor::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $before = $user->only(['name', 'email', 'is_active']);
        $user->update($data);
        AuditLog::record($request->user(), 'user.updated', $user, $before, $data);

        if ($request->boolean('is_administrator')) {
            $user->assignRole('administrator');
        } else {
            $user->removeRole('administrator');
        }

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }
}
