<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_id'             => 'required|exists:companies,id',
            // unique:employees queries the raw table (not through Eloquent's
            // SoftDeletes scope), so this also catches an ID still held by a
            // soft-deleted employee — without it, that case fell straight
            // through to a raw DB UniqueConstraintViolationException instead
            // of a normal validation error.
            'employee_id'            => 'nullable|string|max:20|unique:employees,employee_id',
            // Branch = physical office (Work from Office / Hybrid); Location =
            // where a Work from Home or Client Location employee is based
            // (Work from Home / Hybrid / Client Location, alongside client_name).
            'branch_id'              => 'nullable|required_if:work_type,wfo,hybrid|exists:branches,id',
            'location_id'            => 'nullable|required_if:work_type,wfh,hybrid,client_location|exists:locations,id',
            'department_id'          => 'required|exists:departments,id',
            'designation_id'         => 'required|exists:designations,id',
            'grade_id'               => 'nullable|exists:grades,id',
            'cost_center_id'         => 'nullable|exists:cost_centers,id',
            'reporting_manager_id'   => 'required|exists:employees,id',
            'holiday_calendar_id'    => 'nullable|exists:holiday_calendars,id',

            // Personal
            'first_name'             => 'required|string|max:100',
            'middle_name'            => 'nullable|string|max:100',
            'last_name'              => 'required|string|max:100',
            'gender'                 => 'required|in:Male,Female,Other',
            'date_of_birth'          => 'nullable|date|before:today',
            'marital_status'         => 'nullable|string|max:20',
            'nationality'            => 'nullable|string|max:50',
            'is_physically_handicapped' => 'boolean',

            // Contact
            'personal_email'         => 'nullable|email',
            'official_email'         => 'required|email',
            'mobile'                 => 'required|regex:/^\d{10}$/',
            'alternate_phone'        => 'nullable|regex:/^\d{10}$/',
            'current_address'        => 'nullable|string|max:500',

            // Employment
            'date_of_joining'        => 'required|date',
            'employment_type'        => 'required|in:full_time,part_time,contract,intern,consultant,retainer',
            'work_type'              => 'required|in:wfo,wfh,hybrid,client_location',
            'client_name'            => 'nullable|required_if:work_type,client_location|string|max:150',
            'probation_period_months'=> 'required|integer|min:0|max:24',

            // Payroll flags
            'is_pf_applicable'       => 'boolean',
            'is_esic_applicable'     => 'boolean',
            'is_pt_applicable'       => 'boolean',
            'is_lwf_applicable'      => 'boolean',
            'is_tds_applicable'      => 'boolean',
            'tds_regime'             => 'in:old,new',

            // Statutory — optional at creation, can be completed later via profile.
            // uan_number becomes mandatory once PF Applicable is checked.
            'pan_number'             => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'aadhaar_number'         => 'nullable|regex:/^\d{12}$/',
            'uan_number'             => ['nullable', 'required_if:is_pf_applicable,1', 'regex:/^\d{12}$/'],

            // Bank — optional at creation, can be completed later via profile
            'bank_name'              => 'nullable|string|max:100',
            'account_number'         => 'nullable|string|max:30',
            'ifsc_code'              => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_holder_name'    => 'nullable|string|max:200',

            // Emergency contact
            'emergency_contact_name' => 'required|string|max:100',
            'emergency_contact_phone'=> 'required|regex:/^\d{10}$/',

            // Documents — one file per type, keyed by documents[<type>].
            // PAN, Aadhaar, qualification (marksheet), and cancelled cheque
            // are mandatory at creation; the rest stay optional.
            'documents'                    => 'nullable|array',
            'documents.pan'                => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.aadhaar'            => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.marksheet'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.cancelled_cheque'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.reference_letter'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.experience_letter'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.uan_document'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.last_salary_slip'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.other'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.unique'      => 'This Employee ID is already in use — including by a previously deleted employee. Leave it blank to auto-generate a free one, or pick a different ID.',
            'mobile.regex'            => 'Mobile number must be exactly 10 digits.',
            'pan_number.regex'        => 'PAN must be in format: ABCDE1234F',
            'aadhaar_number.regex'    => 'Aadhaar must be 12 digits.',
            'ifsc_code.regex'         => 'IFSC code must be in format: HDFC0001234',
            'uan_number.regex'        => 'UAN must be 12 digits.',
            'uan_number.required_if'  => 'UAN Number is required when PF Applicable is checked.',
            'client_name.required_if' => 'Client Name is required when Work Type is Client Location.',
            'branch_id.required_if'   => 'Branch is required for Work from Office / Hybrid.',
            'location_id.required_if' => 'Location is required for Work from Home / Hybrid / Client Location.',
            'documents.pan.required'              => 'PAN card upload is required.',
            'documents.aadhaar.required'          => 'Aadhaar card upload is required.',
            'documents.marksheet.required'        => 'Qualification document (marksheet) upload is required.',
            'documents.cancelled_cheque.required' => 'Cancelled cheque upload is required.',
        ];
    }
}
