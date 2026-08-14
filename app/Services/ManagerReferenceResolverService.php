<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Whenever an employee is created or rehired, other employees may have been
 * waiting on THEM specifically as a reporting manager / team leader (stored
 * as raw sheet-text in pending_reporting_manager_ref / pending_team_leader_ref
 * because that person didn't exist yet at the time). This resolves those
 * waiting references — regardless of whether the newly-available person was
 * added via bulk import or the manual "Add Employee" form.
 */
class ManagerReferenceResolverService
{
    public function resolveReferencesTo(Employee $newlyAvailable): void
    {
        foreach ([
            'reporting_manager_id' => 'pending_reporting_manager_ref',
            'team_leader_id'       => 'pending_team_leader_ref',
        ] as $idField => $refField) {
            Employee::where('company_id', $newlyAvailable->company_id)
                ->where('id', '!=', $newlyAvailable->id)
                ->whereNotNull($refField)
                ->get()
                ->each(function (Employee $waiting) use ($newlyAvailable, $idField, $refField) {
                    if ($this->referenceMatches($waiting->{$refField}, $newlyAvailable)) {
                        $waiting->update([$idField => $newlyAvailable->id, $refField => null]);
                    }
                });
        }
    }

    /**
     * Full sweep: re-attempt every still-unresolved reference against the
     * CURRENT state of the employees table. Run this after any bulk upload
     * finishes — catches cases the live per-employee resolution (fired by
     * EmployeeObserver as each row is created) couldn't, e.g. the manager
     * already existed from an earlier, unrelated import. Returns how many
     * references it resolved.
     */
    public function resolveAllPending(): int
    {
        $resolved = 0;

        foreach ([
            'reporting_manager_id' => 'pending_reporting_manager_ref',
            'team_leader_id'       => 'pending_team_leader_ref',
        ] as $idField => $refField) {
            Employee::whereNotNull($refField)->get()->each(function (Employee $waiting) use ($idField, $refField, &$resolved) {
                $match = Employee::where('company_id', $waiting->company_id)
                    ->where('id', '!=', $waiting->id)
                    ->get()
                    ->first(fn (Employee $c) => $this->referenceMatches($waiting->{$refField}, $c));

                if ($match) {
                    $waiting->update([$idField => $match->id, $refField => null]);
                    $resolved++;
                }
            });
        }

        return $resolved;
    }

    /**
     * Does this raw sheet reference (an employee code or full name) identify
     * $employee?
     */
    protected function referenceMatches(?string $reference, Employee $employee): bool
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return false;
        }

        if (strcasecmp($reference, (string) $employee->employee_id) === 0) return true;
        if (strcasecmp($reference, (string) $employee->external_emp_id) === 0) return true;

        $fullName = trim(collect([$employee->first_name, $employee->middle_name, $employee->last_name])->filter()->implode(' '));
        return strcasecmp($reference, $fullName) === 0;
    }
}
