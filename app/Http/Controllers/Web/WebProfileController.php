<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════
// MY PROFILE  (Blade page — every authenticated user)
//
// Self-service: view your own account/employee details and update your
// own name, email, and password. Not the admin User Creation page —
// this only ever touches auth()->user(), never another account.
//
// updateEmployeeDetails() additionally lets the employee fill in whatever
// HR left blank at creation (personal contact info, statutory/bank details,
// documents). The fields HR is required to set at creation — company,
// name, gender, official email, mobile, DOJ, employment/work type,
// department, designation, reporting manager, branch, probation period, PF
// applicable — are shown read-only here and deliberately absent from this
// method's validation/update, so a tampered request can't change them.
// ══════════════════════════════════════════════════════════════

class WebProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load([
            'employee.department', 'employee.designation', 'employee.branch',
            'employee.company', 'employee.reportingManager', 'employee.statutoryDetails',
            'employee.primaryBank', 'employee.documents',
            'roles',
        ]);

        return view('profile.show', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => 'required|string|max:150',
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return redirect()->route('profile.show')->with('success', 'Your profile has been updated.');
    }

    public function updateEmployeeDetails(Request $request, EmployeeDocumentService $employeeDocumentService)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $validated = $request->validate([
            'middle_name'               => 'nullable|string|max:100',
            'date_of_birth'             => 'nullable|date|before:today',
            'marital_status'            => 'nullable|string|max:20',
            'nationality'               => 'nullable|string|max:50',
            'personal_email'            => 'nullable|email',
            'alternate_phone'           => 'nullable|regex:/^\d{10}$/',
            'current_address'          => 'nullable|string|max:255',
            'current_city'             => 'nullable|string|max:100',
            'current_state'            => 'nullable|string|max:100',
            'current_pincode'          => 'nullable|string|max:10',
            'permanent_address'        => 'nullable|string|max:255',
            'permanent_city'           => 'nullable|string|max:100',
            'permanent_state'          => 'nullable|string|max:100',
            'permanent_pincode'        => 'nullable|string|max:10',
            'emergency_contact_name'    => 'nullable|string|max:100',
            'emergency_contact_phone'   => 'nullable|regex:/^\d{10}$/',
            'emergency_contact_relation'=> 'nullable|string|max:50',
            'pan_number'                => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'aadhaar_number'            => 'nullable|regex:/^\d{12}$/',
            'uan_number'                => 'nullable|string|max:12',
            'bank_name'                 => 'nullable|string|max:100',
            'account_number'            => 'nullable|string|max:30',
            'ifsc_code'                 => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_holder_name'       => 'nullable|string|max:200',
            'documents'                 => 'nullable|array',
            'documents.*'               => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $employee->update(collect($validated)->only([
            'middle_name', 'date_of_birth', 'marital_status', 'nationality', 'personal_email', 'alternate_phone',
            'current_address', 'current_city', 'current_state', 'current_pincode',
            'permanent_address', 'permanent_city', 'permanent_state', 'permanent_pincode',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        ])->all());

        if (!empty($validated['pan_number']) || !empty($validated['aadhaar_number']) || !empty($validated['uan_number'])) {
            $employee->statutoryDetails()->updateOrCreate(
                ['employee_id' => $employee->id],
                array_filter([
                    'pan_number'     => $validated['pan_number'] ?? null,
                    'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                    'uan_number'     => $validated['uan_number'] ?? null,
                ])
            );
        }

        if (!empty($validated['account_number'])) {
            $employee->bankDetails()->updateOrCreate(
                ['employee_id' => $employee->id, 'is_primary' => true],
                [
                    'bank_name'           => $validated['bank_name'] ?? '',
                    'account_number'      => $validated['account_number'],
                    'ifsc_code'           => $validated['ifsc_code'] ?? '',
                    'account_holder_name' => $validated['account_holder_name'] ?? $employee->full_name,
                    'account_type'        => 'savings',
                    'is_primary'          => true,
                ]
            );
        }

        $employeeDocumentService->storeUploads($employee, $request->file('documents', []), auth()->id());

        return redirect()->route('profile.show')->with('success', 'Your details have been updated.');
    }
}
