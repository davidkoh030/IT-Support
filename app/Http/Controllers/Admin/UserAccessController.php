<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserAccessController extends Controller
{
    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'scope_type' => ['required', 'in:company,project'],
            'scope_id' => ['required', 'integer'],
            'role' => ['required', 'in:member,project_manager,it_agent,it_manager,approver,auditor'],
            'effective_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $grant = AccessGrant::create([
            ...$data,
            'user_id' => $user->id,
            'granted_by_id' => $request->user()->id,
        ]);

        AuditLog::record($request->user(), 'access_grant.created', $grant, null, $data);

        return back()->with('status', 'Access grant added.');
    }

    /**
     * Revocation is timestamped, never deleted, so the record of who had
     * access and when is preserved (section 4/10).
     */
    public function revoke(Request $request, AccessGrant $accessGrant)
    {
        $accessGrant->update(['revoked_at' => now()]);
        AuditLog::record($request->user(), 'access_grant.revoked', $accessGrant, null, ['revoked_at' => now()->toIso8601String()]);

        return back()->with('status', 'Access grant revoked.');
    }
}
