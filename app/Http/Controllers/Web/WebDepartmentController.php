<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Entity;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebDepartmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('department_view');

        $companyId   = $this->resolveCompanyId($request);
        $departments = Department::where('company_id', $companyId)->orderBy('name')->get();
        $companies   = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('masters.departments', compact('departments', 'companyId', 'companies'));
    }

    public function indexDesignations(Request $request)
    {
        $this->authorize('department_view');

        $companyId    = $this->resolveCompanyId($request);
        $designations = Designation::where('company_id', $companyId)->orderBy('name')->get();
        $companies    = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('masters.designations', compact('designations', 'companyId', 'companies'));
    }

    public function indexBranches(Request $request)
    {
        $this->authorize('branch_view');

        $companyId = $this->resolveCompanyId($request);
        $branches  = Branch::where('company_id', $companyId)->orderBy('name')->get();

        return view('masters.branches', compact('branches', 'companyId'));
    }

    public function indexLocations(Request $request)
    {
        $this->authorize('location_view');

        $companyId = $this->resolveCompanyId($request);
        $locations = Location::where('company_id', $companyId)->orderBy('name')->get();

        return view('masters.locations', compact('locations', 'companyId'));
    }

    public function storeBranch(Request $request)
    {
        $this->authorize('branch_add');

        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'code'    => 'nullable|string|max:20',
            'city'    => 'nullable|string|max:100',
            'state'   => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        Branch::create(array_merge($validated, ['company_id' => $companyId, 'is_active' => true]));
        return redirect()->route('branches')->with('success', 'Branch created.');
    }

    public function updateBranch(Request $request, Branch $branch)
    {
        $this->authorize('branch_add');

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'code'    => 'nullable|string|max:20',
            'city'    => 'nullable|string|max:100',
            'state'   => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        $branch->update($validated);
        return redirect()->route('branches')->with('success', 'Branch updated.');
    }

    public function destroyBranch(Branch $branch)
    {
        $this->authorize('branch_add');

        if (Employee::where('branch_id', $branch->id)->exists()) {
            return redirect()->route('branches')->with('error', "Can't delete \"{$branch->name}\" — it's assigned to one or more employees.");
        }

        $branch->delete();
        return redirect()->route('branches')->with('success', 'Branch deleted.');
    }

    public function bulkDestroyBranches(Request $request)
    {
        $this->authorize('branch_add');

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:branches,id',
        ]);

        [$deleted, $skipped] = $this->bulkDeleteWithEmployeeGuard(Branch::class, 'branch_id', $validated['ids']);

        return redirect()->route('branches')->with(
            $deleted > 0 ? 'success' : 'warning',
            "{$deleted} branch(es) deleted." . ($skipped > 0 ? " {$skipped} skipped (still assigned to employees)." : '')
        );
    }

    /** JSON list of active branches for a given company — used to keep the Add/Edit Employee branch dropdown in sync with whichever company is selected in the modal. */
    public function branchesByCompany(Request $request)
    {
        $this->authorize('employee_view');

        $request->validate(['company_id' => 'required|exists:companies,id']);

        return response()->json(
            Branch::where('company_id', $request->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function storeLocation(Request $request)
    {
        $this->authorize('location_add');

        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'name' => 'required|string|in:' . implode(',', Location::CITIES),
        ]);

        Location::create([
            'company_id' => $companyId,
            'name'       => $validated['name'],
            'is_active'  => $request->boolean('is_active'),
        ]);
        return redirect()->route('locations')->with('success', 'Location created.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        $this->authorize('location_add');

        $validated = $request->validate([
            'name' => 'required|string|in:' . implode(',', Location::CITIES),
        ]);

        $location->update([
            'name'      => $validated['name'],
            'is_active' => $request->boolean('is_active'),
        ]);
        return redirect()->route('locations')->with('success', 'Location updated.');
    }

    public function destroyLocation(Location $location)
    {
        $this->authorize('location_add');

        if (Employee::where('location_id', $location->id)->exists()) {
            return redirect()->route('locations')->with('error', "Can't delete \"{$location->name}\" — it's assigned to one or more employees.");
        }

        $location->delete();
        return redirect()->route('locations')->with('success', 'Location deleted.');
    }

    public function bulkDestroyLocations(Request $request)
    {
        $this->authorize('location_add');

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:locations,id',
        ]);

        [$deleted, $skipped] = $this->bulkDeleteWithEmployeeGuard(Location::class, 'location_id', $validated['ids']);

        return redirect()->route('locations')->with(
            $deleted > 0 ? 'success' : 'warning',
            "{$deleted} location(s) deleted." . ($skipped > 0 ? " {$skipped} skipped (still assigned to employees)." : '')
        );
    }

    /** JSON list of active locations for a given company — used to keep the Add/Edit Employee location dropdown in sync with whichever company is selected in the modal. */
    public function locationsByCompany(Request $request)
    {
        $this->authorize('employee_view');

        $request->validate(['company_id' => 'required|exists:companies,id']);

        return response()->json(
            Location::where('company_id', $request->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /** JSON list of active departments for a given company — used to keep the Add/Edit Employee department dropdown in sync with whichever company is selected in the modal. */
    public function departmentsByCompany(Request $request)
    {
        $this->authorize('employee_view');

        $request->validate(['company_id' => 'required|exists:companies,id']);

        return response()->json(
            Department::where('company_id', $request->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /** JSON list of active designations for a given company — used to keep the Add/Edit Employee designation dropdown in sync with whichever company is selected in the modal. */
    public function designationsByCompany(Request $request)
    {
        $this->authorize('employee_view');

        $request->validate(['company_id' => 'required|exists:companies,id']);

        return response()->json(
            Designation::where('company_id', $request->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function storeDepartment(Request $request)
    {
        $this->authorize('department_add');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:100',
            'code'       => 'required|string|max:10',
            'parent_id'  => 'nullable|exists:departments,id',
        ]);

        Department::create(array_merge($validated, ['is_active' => true]));
        return redirect()->route('departments')->with('success', 'Department created.');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $this->authorize('department_add');

        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:10',
            'parent_id' => 'nullable|exists:departments,id|not_in:' . $department->id,
        ]);

        $department->update($validated);
        return redirect()->route('departments')->with('success', 'Department updated.');
    }

    public function destroyDepartment(Department $department)
    {
        $this->authorize('department_add');

        if (Employee::where('department_id', $department->id)->exists()) {
            return redirect()->route('departments')->with('error', "Can't delete \"{$department->name}\" — it's assigned to one or more employees.");
        }
        if (Department::where('parent_id', $department->id)->exists()) {
            return redirect()->route('departments')->with('error', "Can't delete \"{$department->name}\" — other departments have it set as their parent.");
        }

        $department->delete();
        return redirect()->route('departments')->with('success', 'Department deleted.');
    }

    public function bulkDestroyDepartments(Request $request)
    {
        $this->authorize('department_add');

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:departments,id',
        ]);

        $deleted = 0;
        $skipped = 0;
        foreach (Department::whereIn('id', $validated['ids'])->get() as $department) {
            $inUse = Employee::where('department_id', $department->id)->exists()
                || Department::where('parent_id', $department->id)->exists();
            if ($inUse) {
                $skipped++;
                continue;
            }
            $department->delete();
            $deleted++;
        }

        return redirect()->route('departments')->with(
            $deleted > 0 ? 'success' : 'warning',
            "{$deleted} department(s) deleted." . ($skipped > 0 ? " {$skipped} skipped (still in use)." : '')
        );
    }

    public function storeDesignation(Request $request)
    {
        $this->authorize('designation_manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:100',
            'code'       => 'required|string|max:10',
            'level'      => 'nullable|integer|min:1',
        ]);

        Designation::create(array_merge($validated, ['is_active' => true]));
        return redirect()->route('designations')->with('success', 'Designation created.');
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        $this->authorize('designation_manage');

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'code'  => 'required|string|max:10',
            'level' => 'nullable|integer|min:1',
        ]);

        $designation->update($validated);
        return redirect()->route('designations')->with('success', 'Designation updated.');
    }

    public function destroyDesignation(Designation $designation)
    {
        $this->authorize('designation_manage');

        if (Employee::where('designation_id', $designation->id)->exists()) {
            return redirect()->route('designations')->with('error', "Can't delete \"{$designation->name}\" — it's assigned to one or more employees.");
        }

        $designation->delete();
        return redirect()->route('designations')->with('success', 'Designation deleted.');
    }

    public function bulkDestroyDesignations(Request $request)
    {
        $this->authorize('designation_manage');

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:designations,id',
        ]);

        [$deleted, $skipped] = $this->bulkDeleteWithEmployeeGuard(Designation::class, 'designation_id', $validated['ids']);

        return redirect()->route('designations')->with(
            $deleted > 0 ? 'success' : 'warning',
            "{$deleted} designation(s) deleted." . ($skipped > 0 ? " {$skipped} skipped (still assigned to employees)." : '')
        );
    }

    /** Shared bulk-delete guard for masters that only need the "not referenced by any employee" check (branches, designations) — departments also guard against child departments, so it has its own inline loop. */
    protected function bulkDeleteWithEmployeeGuard(string $modelClass, string $employeeColumn, array $ids): array
    {
        $deleted = 0;
        $skipped = 0;
        foreach ($modelClass::whereIn('id', $ids)->get() as $record) {
            if (Employee::where($employeeColumn, $record->id)->exists()) {
                $skipped++;
                continue;
            }
            $record->delete();
            $deleted++;
        }

        return [$deleted, $skipped];
    }

    public function template()
    {
        $this->authorize('department_add');

        $spreadsheet = new Spreadsheet();

        // Sheet 1 — Departments
        $deptSheet = $spreadsheet->getActiveSheet()->setTitle('Departments');
        foreach (['Name', 'Code', 'Parent Department Code'] as $i => $h) {
            $deptSheet->setCellValue([$i + 1, 1], $h);
        }
        $deptSheet->fromArray(['Engineering', 'ENG', ''], null, 'A2');
        $deptSheet->fromArray(['Frontend', 'FE', 'ENG'], null, 'A3');

        // Sheet 2 — Designations
        $desigSheet = $spreadsheet->createSheet()->setTitle('Designations');
        foreach (['Name', 'Code', 'Level'] as $i => $h) {
            $desigSheet->setCellValue([$i + 1, 1], $h);
        }
        $desigSheet->fromArray(['Software Engineer', 'SE', 3], null, 'A2');
        $desigSheet->fromArray(['Senior Engineer', 'SSE', 4], null, 'A3');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'departments_designations_template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $this->authorize('department_add');

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        $companyId = $this->resolveCompanyId($request);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
            $count = 0;

            DB::beginTransaction();

            // Sheet 1: Departments
            $deptSheet = $spreadsheet->getSheetByName('Departments') ?? $spreadsheet->getSheet(0);
            $deptRows  = $deptSheet->toArray(null, true, true, false);
            array_shift($deptRows);
            $deptCodeMap = [];
            foreach ($deptRows as $row) {
                if (empty(array_filter($row))) continue;
                [$name, $code, $parentCode] = array_pad($row, 3, null);
                if (!$name || !$code) continue;
                $parentId = $parentCode && isset($deptCodeMap[strtoupper($parentCode)])
                    ? $deptCodeMap[strtoupper($parentCode)] : null;
                $dept = Department::updateOrCreate(
                    ['company_id' => $companyId, 'code' => strtoupper(trim($code))],
                    ['name' => trim($name), 'parent_id' => $parentId, 'is_active' => true]
                );
                $deptCodeMap[strtoupper(trim($code))] = $dept->id;
                $count++;
            }

            // Sheet 2: Designations
            $desigSheet = $spreadsheet->getSheetByName('Designations') ?? $spreadsheet->getSheet(1);
            if ($desigSheet) {
                $desigRows = $desigSheet->toArray(null, true, true, false);
                array_shift($desigRows);
                foreach ($desigRows as $row) {
                    if (empty(array_filter($row))) continue;
                    [$name, $code, $level] = array_pad($row, 3, null);
                    if (!$name || !$code) continue;
                    Designation::updateOrCreate(
                        ['company_id' => $companyId, 'code' => strtoupper(trim($code))],
                        ['name' => trim($name), 'level' => $level ? (int)$level : null, 'is_active' => true]
                    );
                    $count++;
                }
            }

            DB::commit();
            return redirect()->route('departments')->with('success', "{$count} departments/designations imported.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('departments')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
