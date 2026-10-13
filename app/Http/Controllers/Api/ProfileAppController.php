<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Mobile mirror of WebProfileController — same scoping (auth()->user()
 * only, never another account), same field set and validation rules.
 * Document uploads are deliberately left out of updateEmployeeDetails()
 * here, same as ReimbursementAppController's submit() being view-only for
 * now — both need a photo/file picker, a native-dependency decision not
 * yet made this session (see conversation/APK_PROGRESS.md).
 */
class ProfileAppController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'employee.department', 'employee.designation', 'employee.branch',
            'employee.company', 'employee.reportingManager', 'employee.statutoryDetails',
            'employee.primaryBank',
            'roles',
        ]);

        $employee = $user->employee;

        return response()->json([
            'data' => [
                'account' => [
                    'name'  => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                ],
                'employee' => $employee ? [
                    'employee_id'            => $employee->employee_id,
                    'full_name'              => $employee->full_name,
                    'company'                => $employee->company?->name,
                    'department'             => $employee->department?->name,
                    'designation'            => $employee->designation?->name,
                    'branch'                 => $employee->branch?->name,
                    'reporting_manager'      => $employee->reportingManager?->full_name,
                    'date_of_joining'        => $employee->date_of_joining,
                    'work_type'              => $employee->work_type,
                    'official_email'         => $employee->official_email,
                    'mobile'                 => $employee->mobile,
                    'gender'                 => $employee->gender,
                    'middle_name'            => $employee->middle_name,
                    'date_of_birth'          => $employee->date_of_birth,
                    'marital_status'         => $employee->marital_status,
                    'nationality'            => $employee->nationality,
                    'personal_email'         => $employee->personal_email,
                    'alternate_phone'        => $employee->alternate_phone,
                    'current_address'        => $employee->current_address,
                    'current_city'           => $employee->current_city,
                    'current_state'          => $employee->current_state,
                    'current_pincode'        => $employee->current_pincode,
                    'permanent_address'      => $employee->permanent_address,
                    'permanent_city'         => $employee->permanent_city,
                    'permanent_state'        => $employee->permanent_state,
                    'permanent_pincode'      => $employee->permanent_pincode,
                    'emergency_contact_name'     => $employee->emergency_contact_name,
                    'emergency_contact_phone'    => $employee->emergency_contact_phone,
                    'emergency_contact_relation' => $employee->emergency_contact_relation,
                    'pan_number'             => $employee->statutoryDetails?->pan_number,
                    'aadhaar_number'         => $employee->statutoryDetails?->aadhaar_number,
                    'uan_number'             => $employee->statutoryDetails?->uan_number,
                    'bank_name'              => $employee->primaryBank?->bank_name,
                    'account_number'         => $employee->primaryBank?->account_number,
                    'ifsc_code'              => $employee->primaryBank?->ifsc_code,
                    'account_holder_name'    => $employee->primaryBank?->account_holder_name,
                ] : null,
            ],
        ]);
    }

    /** Account-level: name, email, password — same as WebProfileController::update(). */
    public function update(Request $request): JsonResponse
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

        return response()->json(['data' => ['name' => $user->name, 'email' => $user->email]]);
    }

    /** Employee-level self-editable fields — same whitelist as WebProfileController::updateEmployeeDetails(), minus document uploads. */
    public function updateEmployeeDetails(Request $request): JsonResponse
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

        return response()->json(['data' => ['updated' => true]]);
    }
}
