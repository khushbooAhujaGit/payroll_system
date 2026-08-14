<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\ManagerReferenceResolverService;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        app(ManagerReferenceResolverService::class)->resolveReferencesTo($employee);
    }

    public function restored(Employee $employee): void
    {
        app(ManagerReferenceResolverService::class)->resolveReferencesTo($employee);
    }
}
