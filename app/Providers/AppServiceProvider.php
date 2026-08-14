<?php

namespace App\Providers;

use App\Models\Employee;
use App\Observers\EmployeeObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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

        // Whenever an employee is created/rehired (manual form OR bulk
        // import), resolve anyone else still waiting on them as a manager —
        // see ManagerReferenceResolverService.
        Employee::observe(EmployeeObserver::class);

        // spatie/laravel-permission wires $user->can('some.permission') into
        // Gate automatically (via its own Gate::before) — no custom permission
        // lookup needed here. This one just adds the "Admin always gets
        // everything, regardless of what's actually assigned" bypass rule —
        // see User::isSuperAdmin().
        Gate::before(function ($user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        // Feeds the header bell/dropdown on every authenticated page —
        // see resources/views/layouts/app.blade.php.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with([
                'recentNotifications' => $user ? $user->notifications()->latest()->take(10)->get() : collect(),
                'unreadNotifCount'    => $user ? $user->unreadNotifications()->count() : 0,
            ]);
        });
    }
}
