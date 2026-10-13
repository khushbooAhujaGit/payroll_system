<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'branch_id'              => 'sometimes|nullable|exists:branches,id',
            'location_id'            => 'sometimes|nullable|exists:locations,id',
            'department_id'          => 'sometimes|nullable|exists:departments,id',
            'designation_id'         => 'sometimes|nullable|exists:designations,id',
            'grade_id'               => 'sometimes|nullable|exists:grades,id',
            'cost_center_id'         => 'sometimes|nullable|exists:cost_centers,id',
            'reporting_manager_id'   => 'sometimes|nullable|exists:employees,id',
            'holiday_calendar_id'    => 'sometimes|nullable|exists:holiday_calendars,id',

            'first_name'             => 'sometimes|string|max:100',
            'middle_name'            => 'sometimes|nullable|string|max:100',
            'last_name'              => 'sometimes|string|max:100',
            'gender'                 => 'sometimes|in:Male,Female,Other',
            'date_of_birth'          => 'sometimes|nullable|date|before:today',
            'blood_group'            => 'sometimes|nullable|string|max:5',
            'marital_status'         => 'sometimes|nullable|string|max:20',

            'personal_email'         => 'sometimes|nullable|email',
            'official_email'         => 'sometimes|nullable|email',
            'mobile'                 => 'sometimes|regex:/^\d{10}$/',
            'alternate_phone'        => 'sometimes|nullable|regex:/^\d{10}$/',

            'current_address'        => 'sometimes|nullable|string',
            'current_city'           => 'sometimes|nullable|string|max:100',
            'current_state'          => 'sometimes|nullable|string|max:100',
            'current_pincode'        => 'sometimes|nullable|string|max:10',
            'permanent_address'      => 'sometimes|nullable|string',
            'permanent_city'         => 'sometimes|nullable|string|max:100',
            'permanent_state'        => 'sometimes|nullable|string|max:100',
            'permanent_pincode'      => 'sometimes|nullable|string|max:10',

            'employment_type'        => 'sometimes|in:full_time,part_time,contract,intern,consultant,retainer',
            'work_type'              => 'sometimes|in:wfo,wfh,hybrid,client_location',
            'employee_status'        => 'sometimes|in:active,inactive,on_notice,absconding,suspended,on_leave',
            'tds_regime'             => 'sometimes|in:old,new',

            'is_pf_applicable'       => 'sometimes|boolean',
            'is_esic_applicable'     => 'sometimes|boolean',
            'is_pt_applicable'       => 'sometimes|boolean',
            'is_lwf_applicable'      => 'sometimes|boolean',
            'is_tds_applicable'      => 'sometimes|boolean',
            'is_payroll_active'      => 'sometimes|boolean',

            'emergency_contact_name' => 'sometimes|nullable|string|max:100',
            'emergency_contact_phone'=> 'sometimes|nullable|regex:/^\d{10}$/',
            'emergency_contact_relation' => 'sometimes|nullable|string|max:50',
        ];
    }
}
