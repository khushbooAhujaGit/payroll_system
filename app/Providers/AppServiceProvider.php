<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::defaultView('vendor.pagination.hrms');
        // Wire our custom RBAC into Laravel's Gate system.
        // super_admin bypasses all checks.
        // All other users are checked against the role_permissions table.
        Gate::before(function ($user, string $ability) {
            // Super admin gets everything
            if ($user->roles()->where('slug', 'super_admin')->exists()) {
                return true;
            }

            // Check if this user has the permission slug via any of their roles
            $hasPermission = $user->roles()
                ->whereHas('permissions', fn($q) => $q->where('slug', $ability))
                ->exists();

            if ($hasPermission) {
                return true;
            }

            // Return null (not false) to fall through to other Gate checks
            return null;
        });
    }
}
