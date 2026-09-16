<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrator') || $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager', 'auditor'])->exists();
    }

    public function view(User $user, Asset $asset): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($asset->assigned_user_id === $user->id) {
            return true;
        }

        return $user->hasScopedRole('it_agent', 'company', $asset->company_id)
            || $user->hasScopedRole('it_manager', 'company', $asset->company_id)
            || ($asset->project_id !== null && ($user->hasScopedRole('it_agent', 'project', $asset->project_id) || $user->hasScopedRole('it_manager', 'project', $asset->project_id)));
    }

    public function manage(User $user, ?Asset $asset = null): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($asset === null) {
            return $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->exists();
        }

        return $user->hasScopedRole('it_agent', 'company', $asset->company_id) || $user->hasScopedRole('it_manager', 'company', $asset->company_id);
    }
}
