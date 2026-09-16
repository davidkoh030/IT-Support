<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Administrators, or anyone holding an active it_manager grant on
        // any company/project, may reach the admin section. Individual
        // admin controllers still re-check scope per record.
        Gate::define('manage-admin', function (User $user) {
            return $user->hasRole('administrator')
                || $user->activeAccessGrants()->where('role', 'it_manager')->exists();
        });
    }
}
