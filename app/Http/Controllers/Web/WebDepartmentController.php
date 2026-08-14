<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Entity;
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

        return view('masters.departments', compact('departments', 'companyId'));
    }

    public function indexDesignations(Request $request)
    {
        $this->authorize('department_view');

        $companyId    = $this->resolveCompanyId($request);
        $designations = Designation::where('company_id', $companyId)->orderBy('name')->get();

        return view('masters.designations', compact('designations', 'companyId'));
    }

    public function indexBranches(Request $request)
    {
        $this->authorize('branch_view');

        $companyId = $this->resolveCompanyId($request);
        $branches  = Branch::where('company_id', $companyId)->orderBy('name')->get();

        return view('masters.branches', compact('branches', 'companyId'));
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

    public function storeDepartment(Request $request)
    {
        $this->authorize('department_add');

        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:10',
            'parent_id' => 'nullable|exists:departments,id',
        ]);

        Department::create(array_merge($validated, ['company_id' => $companyId, 'is_active' => true]));
        return redirect()->route('departments')->with('success', 'Department created.');
    }

    public function storeDesignation(Request $request)
    {
        $this->authorize('designation_manage');

        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'code'  => 'required|string|max:10',
            'level' => 'nullable|integer|min:1',
        ]);

        Designation::create(array_merge($validated, ['company_id' => $companyId, 'is_active' => true]));
        return redirect()->route('designations')->with('success', 'Designation created.');
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
