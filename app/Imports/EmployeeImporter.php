<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesExcelDates;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeDocument;
use App\Models\EmployeeStatutoryDetail;
use App\Models\Entity;
use App\Models\Grade;
use App\Models\Location;
use App\Services\EmployeeService;
use App\Services\UserProvisioningService;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Imports employees from the standard Excel template.
 *
 * Required columns: New Emp ID, Employee Name, Join Date
 * "Employee Name" is split into first / middle / last automatically.
 */
class EmployeeImporter extends BaseImporter
{
    use ParsesExcelDates;

    /**
     * Tracks employee_ids seen in THIS upload to detect in-file duplicates.
     * Key = employee_id string, Value = first row number it appeared in.
     */
    protected array $seenEmpIds   = [];

    /**
     * PANs seen in THIS upload — detects duplicates within the file.
     * Key = PAN string (uppercase), Value = first row number it appeared in.
     */
    protected array $seenPans = [];

    // ─────────────────────────────────────────────
    // TEMPLATE DEFINITION  (mirrors the real template exactly)
    // ─────────────────────────────────────────────

    

    /**
     * Mirrors the manual "Add Employee" form's field set/order exactly where
     * a field exists there. Team Leader, Attendance, and Location (City)
     * aren't on that form but are real, already-used fields — kept as
     * bulk-only extras rather than silently dropped. The *Document Link
     * columns take a OneDrive share link per document type (same types as
     * the manual form's uploads) — stored as a pending reference on
     * EmployeeDocument.source_url, not downloaded yet (see class docblock).
     */
    public function getTemplateHeaders(): array
    {
        return [
            'Company', 'Employee ID',
            'First Name', 'Middle Name', 'Last Name', 'Gender', 'Date of Birth',
            'Marital Status', 'Nationality',
            'Official Email', 'Mobile Number', 'Personal Email', 'Alternate Phone', 'Current Address',
            'Date of Joining', 'Employment Type', 'Work Type', 'Client Name',
            'Department', 'Designation', 'Branch', 'Work Location', 'Reporting Manager', 'Team Leader',
            'Probation Period (Months)',
            'PF Applicable', 'PAN', 'Aadhaar Number', 'UAN Number',
            'Bank Name', 'Account Number', 'IFSC Code', 'Account Holder Name',
            'Emergency Contact Name', 'Emergency Contact Phone',
            'Offer Letter Date', 'Attendance Group', 'Location (City)',
            'PAN Document Link', 'Aadhaar Document Link', 'Marksheet Document Link',
            'Reference Letter Document Link', 'Experience Letter Document Link',
            'Cancelled Cheque Document Link', 'UAN Document Link',
            'Last Salary Slip Document Link', 'Other Document Link',
        ];
    }


    public function getSampleRows(): array
    {
        return []; // empty template — headers only
    }


    protected function getRequiredHeaders(): array
    {
        // Columns that must exist in the sheet — every column validate()
        // enforces per row, matching StoreEmployeeRequest's required rules
        // (see that class + validate()'s docblock for the one deliberate
        // exception: Reporting Manager doesn't have to already exist yet).
        return [
            'Company', 'Employee ID', 'First Name', 'Last Name', 'Gender',
            'Official Email', 'Mobile Number', 'Date of Joining',
            'Employment Type', 'Work Type', 'Department', 'Designation',
            'Branch', 'Work Location', 'Reporting Manager', 'Probation Period (Months)',
            'Emergency Contact Name', 'Emergency Contact Phone',
            'PAN Document Link', 'Aadhaar Document Link',
            'Marksheet Document Link', 'Cancelled Cheque Document Link',
        ];
    }



    public function getColumnValidations(): array
    {
        return [
            'Employee ID'       => ['required' => true,  'format' => 'Your company employee ID', 'example' => '201820001'],
            'Company'           => ['required' => true,  'format' => 'Existing company name, or a new one to create it', 'example' => 'Acme Corp'],
            'First Name'        => ['required' => true,  'format' => 'Text', 'example' => 'Rajesh'],
            'Last Name'         => ['required' => true,  'format' => 'Text', 'example' => 'Sharma'],
            'Gender'            => ['required' => true,  'format' => 'Male / Female / Other', 'example' => 'Male'],
            'Date of Birth'     => ['required' => false, 'format' => 'DD-MM-YYYY', 'example' => '15-06-1990'],
            'Official Email'    => ['required' => true,  'format' => 'Email address', 'example' => 'rajesh@company.com'],
            'Mobile Number'     => ['required' => true,  'format' => '10 digit number', 'example' => '9876543210'],
            'Alternate Phone'   => ['required' => false, 'format' => '10 digit number', 'example' => '9876543211'],
            'Date of Joining'   => ['required' => true,  'format' => 'DD-MM-YYYY', 'example' => '01-01-2024'],
            'Employment Type'   => ['required' => true,  'format' => 'Full Time / Part Time / Contract / Intern / Consultant / Retainer', 'example' => 'Full Time'],
            'Work Type'         => ['required' => true,  'format' => 'Work from Office / Work from Home / Hybrid / Client Location', 'example' => 'Work from Office'],
            'Client Name'       => ['required' => false, 'format' => 'Required only if Work Type is Client Location', 'example' => 'Acme Corp'],
            'Department'        => ['required' => true,  'format' => 'Existing department name, or a new one to create it', 'example' => 'Finance'],
            'Designation'       => ['required' => true,  'format' => 'Existing designation name, or a new one to create it', 'example' => 'Executive'],
            'Branch'            => ['required' => false, 'format' => 'Existing branch name, or a new one to create it — required for Work from Office / Hybrid', 'example' => 'Ahmedabad HQ'],
            'Work Location'     => ['required' => false, 'format' => 'Existing work location name, or a new one to create it — required for Work from Home / Hybrid', 'example' => 'Remote - Ahmedabad'],
            'Reporting Manager' => ['required' => true,  'format' => 'Their Employee ID — can be someone else in this same file, resolved automatically once created', 'example' => '201820001'],
            'Probation Period (Months)' => ['required' => true, 'format' => 'Number, 0-24', 'example' => '6'],
            'PF Applicable'     => ['required' => false, 'format' => 'Yes / No', 'example' => 'Yes'],
            'PAN'               => ['required' => false, 'format' => '10 char alphanumeric (ABCDE1234F)', 'example' => 'ABCDE1234F'],
            'Aadhaar Number'    => ['required' => false, 'format' => '12 digit number', 'example' => '123456789012'],
            'UAN Number'        => ['required' => false, 'format' => '12 digit number — required if PF Applicable is Yes', 'example' => '101234567890'],
            'Bank Name'         => ['required' => false, 'format' => 'Bank name', 'example' => 'HDFC Bank'],
            'Account Number'    => ['required' => false, 'format' => 'Numeric', 'example' => '50100123456789'],
            'IFSC Code'         => ['required' => false, 'format' => '11 char (HDFC0001234)', 'example' => 'HDFC0001234'],
            'Emergency Contact Name'  => ['required' => true,  'format' => 'Text', 'example' => 'Ramesh Sharma'],
            'Emergency Contact Phone' => ['required' => true,  'format' => '10 digit number', 'example' => '9876543212'],
            'Offer Letter Date' => ['required' => false, 'format' => 'DD-MM-YYYY', 'example' => '01-12-2023'],
            'PAN Document Link'              => ['required' => true, 'format' => 'A OneDrive share link — saved for later download, not fetched automatically yet', 'example' => 'https://1drv.ms/...'],
            'Aadhaar Document Link'          => ['required' => true, 'format' => 'Same as PAN Document Link', 'example' => 'https://1drv.ms/...'],
            'Marksheet Document Link'        => ['required' => true, 'format' => 'Same as PAN Document Link', 'example' => 'https://1drv.ms/...'],
            'Cancelled Cheque Document Link' => ['required' => true, 'format' => 'Same as PAN Document Link', 'example' => 'https://1drv.ms/...'],
            '*Other Document Link columns' => ['required' => false, 'format' => 'A OneDrive share link — saved for later download, not fetched automatically yet', 'example' => 'https://1drv.ms/...'],
        ];
    }


    // ─────────────────────────────────────────────
    // ROW MAPPING
    // ─────────────────────────────────────────────

    public function mapRow(array $row, array $headers): array
    {
        $mapped = [];
        foreach ($headers as $col => $header) {
            $value = $row[$col] ?? null;
            $field = $this->headerToField(trim((string) $header));
            $mapped[$field] = is_string($value) ? trim($value) : $value;
        }
        return $mapped;
    }

    protected function headerToField(string $header): string
    {
        $map = [
            'Company'                          => 'firm',
            'Employee ID'                      => 'new_emp_id',
            'First Name'                       => 'first_name',
            'Middle Name'                      => 'middle_name',
            'Last Name'                        => 'last_name',
            'Gender'                           => 'gender',
            'Date of Birth'                    => 'date_of_birth',
            'Marital Status'                   => 'marital_status',
            'Nationality'                      => 'nationality',
            'Official Email'                   => 'official_email',
            'Mobile Number'                    => 'mobile',
            'Personal Email'                   => 'personal_email',
            'Alternate Phone'                  => 'alternate_phone',
            'Current Address'                  => 'current_address',
            'Date of Joining'                  => 'date_of_joining',
            'Employment Type'                  => 'employment_type',
            'Work Type'                        => 'work_type',
            'Client Name'                      => 'client_name',
            'Department'                       => 'department',
            'Designation'                      => 'designation',
            'Branch'                           => 'branch',
            'Work Location'                    => 'location',
            'Reporting Manager'                => 'reporting_manager',
            'Team Leader'                      => 'team_leader',
            'Probation Period (Months)'        => 'probation_period_months',
            'PF Applicable'                    => 'pf_applicability',
            'PAN'                              => 'pan',
            'Aadhaar Number'                   => 'aadhaar_number',
            'UAN Number'                       => 'uan_number',
            'Bank Name'                        => 'bank_name',
            'Account Number'                   => 'account_number',
            'IFSC Code'                        => 'ifsc_code',
            'Account Holder Name'              => 'account_holder_name',
            'Emergency Contact Name'           => 'emergency_contact_name',
            'Emergency Contact Phone'          => 'emergency_contact_phone',
            'Offer Letter Date'                => 'offer_letter_date',
            'Attendance Group'                 => 'attendance_group',
            'Location (City)'                  => 'current_city',
            'PAN Document Link'                => 'pan_doc_link',
            'Aadhaar Document Link'            => 'aadhaar_doc_link',
            'Marksheet Document Link'          => 'marksheet_doc_link',
            'Reference Letter Document Link'   => 'reference_letter_doc_link',
            'Experience Letter Document Link'  => 'experience_letter_doc_link',
            'Cancelled Cheque Document Link'   => 'cancelled_cheque_doc_link',
            'UAN Document Link'                => 'uan_document_doc_link',
            'Last Salary Slip Document Link'   => 'last_salary_slip_doc_link',
            'Other Document Link'              => 'other_doc_link',

            // Legacy headers — still recognised so an old downloaded
            // template (or a re-upload of a previously exported file)
            // doesn't just silently drop the column.
            'New Emp ID'                       => 'new_emp_id',
            'Employee Name'                    => 'employee_name_legacy',
            'CurrentStatus'                    => 'current_status',
            'Firm'                             => 'firm',
            'Designation Final'                => 'designation',
            'Email address'                    => 'official_email',
            'RSC Mail ID'                      => 'personal_email',
            'PF Applicability'                 => 'pf_applicability',
        ];

        return $map[$header] ?? '_skip_' . strtolower(str_replace([' ', '(', ')'], '_', $header));
    }

    // ─────────────────────────────────────────────
    // VALIDATION
    // ─────────────────────────────────────────────

    /**
     * Mirrors App\Http\Requests\HR\StoreEmployeeRequest's rules field-for-
     * field, so a row that would be rejected on the manual Add Employee
     * form is rejected here too. One deliberate difference: Reporting
     * Manager must be FILLED IN (matching the form), but is not required to
     * already exist in the database at validation time — it can still
     * resolve later via the pending-reference backfill (see process()),
     * so a manager and their new team can be imported together in one file.
     */
    public function validate(array $data, int $rowNumber): array
    {
        $errors   = [];
        $newEmpId = trim((string) ($data['new_emp_id'] ?? ''));

        // ── Required: Employee ID ─────────────────────────────────────────────
        if ($newEmpId === '') {
            $errors[] = "Employee ID is required.";
        } else {
            // Detect duplicate employee IDs within the same upload file
            if (isset($this->seenEmpIds[$newEmpId])) {
                $errors[] = "Duplicate Emp ID \"{$newEmpId}\" — already present in row {$this->seenEmpIds[$newEmpId]} of this file.";
            } else {
                $this->seenEmpIds[$newEmpId] = $rowNumber;
            }
        }

        // ── Required: Company ─────────────────────────────────────────────────
        if (empty($data['firm'])) {
            $errors[] = "Company is required.";
        }

        // ── Required: First Name / Last Name (from either the new columns
        // or, for an old-template row, the legacy Employee Name) ────────────
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName  = trim((string) ($data['last_name'] ?? ''));
        if ($firstName === '' && !empty($data['employee_name_legacy'])) {
            [$firstName, , $lastName] = $this->splitName($data['employee_name_legacy']);
        }
        if ($firstName === '') {
            $errors[] = "First Name is required.";
        }
        if ($lastName === '') {
            $errors[] = "Last Name is required.";
        }

        // ── Required: Gender ──────────────────────────────────────────────────
        $gender = trim((string) ($data['gender'] ?? ''));
        if ($gender === '') {
            $errors[] = "Gender is required.";
        } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors[] = "Gender must be Male, Female, or Other. Got: \"{$gender}\".";
        }

        // ── Required: Official Email ─────────────────────────────────────────
        $officialEmail = trim((string) ($data['official_email'] ?? ''));
        if ($officialEmail === '') {
            $errors[] = "Official Email is required.";
        } elseif (!filter_var($officialEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Official Email is not a valid email address. Got: \"{$officialEmail}\".";
        }

        // ── Required: Mobile Number ──────────────────────────────────────────
        $mobileDigits = preg_replace('/\D/', '', (string) ($data['mobile'] ?? ''));
        if ($mobileDigits === '') {
            $errors[] = "Mobile Number is required.";
        } elseif (strlen($mobileDigits) !== 10) {
            $errors[] = "Mobile Number must be exactly 10 digits. Got: \"{$data['mobile']}\".";
        }

        // ── Required: Date of Joining ────────────────────────────────────────
        if (empty($data['date_of_joining'])) {
            $errors[] = "Date of Joining is required.";
        } elseif ($this->parseDate($data['date_of_joining']) === null) {
            $errors[] = "Date of Joining could not be parsed. Got: \"{$data['date_of_joining']}\". Use DD-MM-YYYY.";
        }

        // ── Required: Employment Type ────────────────────────────────────────
        $employmentTypeRaw = strtolower(trim((string) ($data['employment_type'] ?? '')));
        if ($employmentTypeRaw === '') {
            $errors[] = "Employment Type is required.";
        } elseif (!in_array($employmentTypeRaw, ['full time', 'full_time', 'part time', 'part_time', 'contract', 'intern', 'consultant', 'retainer'], true)) {
            $errors[] = "Employment Type must be one of Full Time / Part Time / Contract / Intern / Consultant / Retainer. Got: \"{$data['employment_type']}\".";
        }

        // ── Required: Work Type ──────────────────────────────────────────────
        $workTypeRaw = strtolower(trim((string) ($data['work_type'] ?? '')));
        if ($workTypeRaw === '') {
            $errors[] = "Work Type is required.";
        } elseif (!in_array($workTypeRaw, ['work from office', 'wfo', 'work from home', 'wfh', 'hybrid', 'client location', 'client_location'], true)) {
            $errors[] = "Work Type must be one of Work from Office / Work from Home / Hybrid / Client Location. Got: \"{$data['work_type']}\".";
        }
        // Client Name required if Work Type is Client Location — same required_if as the form
        if (in_array($workTypeRaw, ['client location', 'client_location'], true) && empty($data['client_name'])) {
            $errors[] = "Client Name is required when Work Type is Client Location.";
        }

        // ── Required: Department / Designation / Branch / Work Location ─────
        if (empty($data['department'])) {
            $errors[] = "Department is required.";
        }
        if (empty($data['designation'])) {
            $errors[] = "Designation is required.";
        }
        // Branch = Work from Office / Hybrid; Work Location = Work from Home /
        // Hybrid — same split as the manual Add Employee form (StoreEmployeeRequest).
        $needsBranch   = in_array($workTypeRaw, ['work from office', 'wfo', 'hybrid'], true);
        $needsLocation = in_array($workTypeRaw, ['work from home', 'wfh', 'hybrid'], true);
        if ($needsBranch && empty($data['branch'])) {
            $errors[] = "Branch is required for Work from Office / Hybrid.";
        }
        if ($needsLocation && empty($data['location'])) {
            $errors[] = "Work Location is required for Work from Home / Hybrid.";
        }

        // ── Required: Reporting Manager — must be filled in, but resolving
        // it against an actual employee record happens in process(), not
        // here, so a manager can be imported alongside their new team. ─────
        if (empty($data['reporting_manager'])) {
            $errors[] = "Reporting Manager is required.";
        }

        // ── Required: Probation Period ───────────────────────────────────────
        if (!is_numeric($data['probation_period_months'] ?? null)) {
            $errors[] = "Probation Period (Months) is required and must be a number.";
        } elseif ((int) $data['probation_period_months'] < 0 || (int) $data['probation_period_months'] > 24) {
            $errors[] = "Probation Period (Months) must be between 0 and 24.";
        }

        // ── Required: Emergency Contact ──────────────────────────────────────
        if (empty($data['emergency_contact_name'])) {
            $errors[] = "Emergency Contact Name is required.";
        }
        $emergencyDigits = preg_replace('/\D/', '', (string) ($data['emergency_contact_phone'] ?? ''));
        if ($emergencyDigits === '') {
            $errors[] = "Emergency Contact Phone is required.";
        } elseif (strlen($emergencyDigits) !== 10) {
            $errors[] = "Emergency Contact Phone must be exactly 10 digits. Got: \"{$data['emergency_contact_phone']}\".";
        }

        // ── Required: the 4 mandatory documents (matching the form's
        // mandatory uploads) — a link, since bulk collects OneDrive links
        // rather than files. ─────────────────────────────────────────────
        foreach ([
            'pan_doc_link'              => 'PAN Document Link',
            'aadhaar_doc_link'          => 'Aadhaar Document Link',
            'marksheet_doc_link'        => 'Marksheet Document Link',
            'cancelled_cheque_doc_link' => 'Cancelled Cheque Document Link',
        ] as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = "{$label} is required.";
            }
        }

        // ── Optional-but-if-present-must-be-valid-format fields (matching
        // the form's regex rules) ────────────────────────────────────────
        $pan = !empty($data['pan']) ? strtoupper(trim((string) $data['pan'])) : null;
        if ($pan && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            $errors[] = "PAN must be in format ABCDE1234F. Got: \"{$data['pan']}\".";
        }
        $aadhaarDigits = !empty($data['aadhaar_number']) ? preg_replace('/\D/', '', (string) $data['aadhaar_number']) : null;
        if ($aadhaarDigits !== null && strlen($aadhaarDigits) !== 12) {
            $errors[] = "Aadhaar Number must be 12 digits. Got: \"{$data['aadhaar_number']}\".";
        }
        if (!empty($data['ifsc_code']) && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper(trim($data['ifsc_code'])))) {
            $errors[] = "IFSC Code must be in format HDFC0001234. Got: \"{$data['ifsc_code']}\".";
        }
        $uanDigits = !empty($data['uan_number']) ? preg_replace('/\D/', '', (string) $data['uan_number']) : null;
        $isPfApplicable = $this->parseBool($data['pf_applicability'] ?? 'No');
        if ($isPfApplicable && ($uanDigits === null || $uanDigits === '')) {
            $errors[] = "UAN Number is required when PF Applicable is Yes.";
        } elseif ($uanDigits !== null && strlen($uanDigits) !== 12) {
            $errors[] = "UAN Number must be 12 digits. Got: \"{$data['uan_number']}\".";
        }
        $altDigits = !empty($data['alternate_phone']) ? preg_replace('/\D/', '', (string) $data['alternate_phone']) : null;
        if ($altDigits !== null && strlen($altDigits) !== 10) {
            $errors[] = "Alternate Phone must be exactly 10 digits. Got: \"{$data['alternate_phone']}\".";
        }

        // ── PAN — check for duplicates within the file and in the DB ─────────
        if ($pan && $newEmpId !== '') {
            if (isset($this->seenPans[$pan])) {
                $errors[] = "Duplicate PAN \"{$pan}\" — already present in row {$this->seenPans[$pan]} of this file. "
                          . "Each employee must have a unique PAN.";
            } else {
                $this->seenPans[$pan] = $rowNumber;

                // Check if this PAN is already registered to a DIFFERENT employee_id in the DB
                $existingWithPan = Employee::whereHas(
                    'statutoryDetails',
                    fn($q) => $q->where('pan_number_hash', hash('sha256', $pan))
                )->first();

                if ($existingWithPan
                    && $existingWithPan->employee_id !== $newEmpId
                    && $existingWithPan->external_emp_id !== $newEmpId
                ) {
                    $errors[] = "PAN \"{$pan}\" is already registered to employee "
                              . "\"{$existingWithPan->employee_id}\" in the database. "
                              . "Each employee must have a unique PAN.";
                }
            }
        }

        return $errors;
    }

    // ─────────────────────────────────────────────
    // PROCESSING
    // ─────────────────────────────────────────────

    public function process(array $data, int $rowNumber): void
    {
        $companyId = $this->resolveImportCompanyId($data['firm'] ?? null);
        $newEmpId = trim((string) ($data['new_emp_id'] ?? ''));

        if ($newEmpId === '') {
            throw new \RuntimeException("Row {$rowNumber}: Employee ID is empty.");
        }

        // ── Name — prefer the new First/Middle/Last columns; fall back to
        // splitting the legacy single "Employee Name" column for old files ──
        $firstName  = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? '')) ?: null;
        $lastName   = trim((string) ($data['last_name'] ?? ''));
        if ($firstName === '' && !empty($data['employee_name_legacy'])) {
            [$firstName, $middleName, $lastName] = $this->splitName($data['employee_name_legacy']);
        }

        if ($firstName === '') {
            throw new \RuntimeException("Row {$rowNumber}: First Name is missing.");
        }

        // ── Parse dates ──────────────────────────────────────
        $dateOfJoining   = $this->parseDate($data['date_of_joining'] ?? '');
        $dateOfBirth     = $this->parseDate($data['date_of_birth'] ?? '');   // null when non-date value like a name
        $offerLetterDate = $this->parseDate($data['offer_letter_date'] ?? ''); // null when blank or unparseable

        if ($dateOfJoining === null) {
            throw new \RuntimeException("Row {$rowNumber}: Join Date is missing or invalid.");
        }

        // ── Booleans ─────────────────────────────────────────
        $isPfApplicable   = $this->parseBool($data['pf_applicability'] ?? 'No');
        $isEsicApplicable = !empty($data['esic_number']);

        // ── Enum-style fields — map the human labels shown in the Excel
        // dropdown to the DB's stored enum values. Falls back to a sensible
        // default (matching the manual Add Employee form's defaults) on
        // anything unrecognised, rather than failing the whole row over a
        // typo in an optional column — the dropdown is WARNING-style, not
        // enforced, so free text is expected here sometimes.
        $employmentType = $this->mapEmploymentType($data['employment_type'] ?? null);
        $workType        = $this->mapWorkType($data['work_type'] ?? null);
        $maritalStatus   = $this->mapMaritalStatus($data['marital_status'] ?? null);
        $genderRaw       = trim((string) ($data['gender'] ?? ''));
        $gender          = in_array($genderRaw, ['Male', 'Female', 'Other'], true) ? $genderRaw : null;

        // ── Alternate phone — same 10-digit cleanup as mobile, non-blocking ──
        $alternatePhone = !empty($data['alternate_phone'])
            ? preg_replace('/\D/', '', (string) $data['alternate_phone'])
            : null;
        if ($alternatePhone !== null && strlen($alternatePhone) !== 10) {
            $alternatePhone = null;
        }

        $emergencyPhone = !empty($data['emergency_contact_phone'])
            ? preg_replace('/\D/', '', (string) $data['emergency_contact_phone'])
            : null;
        if ($emergencyPhone !== null && strlen($emergencyPhone) !== 10) {
            $emergencyPhone = null;
        }

        $probationMonths = is_numeric($data['probation_period_months'] ?? null)
            ? max(0, min(24, (int) $data['probation_period_months']))
            : 6; // matches the manual form's default

        // ── Normalise status ─────────────────────────────────
        $status = $this->normaliseStatus($data['current_status'] ?? 'active');

        // Marking someone as having left (date_of_leaving) is no longer a
        // bulk-import concern — see WebEmployeeController::markAsLeft() /
        // the "Mark as Left" action on the Employees and My Team pages.
        // date_of_leaving is deliberately left out of $fields below so a
        // re-import never overwrites/clears it for someone already marked.

        // ── Clean mobile — strip non-digits, null if not exactly 10 digits ───
        $mobile = !empty($data['mobile'])
            ? preg_replace('/\D/', '', (string) $data['mobile'])
            : null;
        if ($mobile !== null) {
            if (strlen($mobile) > 10) $mobile = substr($mobile, -10); // drop country code prefix
            if (strlen($mobile) < 10) $mobile = null;                 // too short → discard
        }

        // ── Resolve foreign keys (all silent — null if not found) ──
        $departmentId  = $this->resolveOrCreateMasterId(Department::class, $data['department'] ?? null, $companyId);
        $designationId = $this->resolveOrCreateMasterId(Designation::class, $data['designation'] ?? null, $companyId);
        $branchId      = $this->resolveOrCreateMasterId(Branch::class, $data['branch'] ?? null, $companyId, [
            'city' => $data['current_city'] ?? null,
        ]);
        $locationId    = $this->resolveOrCreateMasterId(Location::class, $data['location'] ?? null, $companyId, [
            'city' => $data['current_city'] ?? null,
        ]);

        $reportingManagerId = $this->resolveEmployee($companyId, $data['reporting_manager'] ?? null);
        $teamLeaderId       = $this->resolveEmployee($companyId, $data['team_leader']       ?? null);

        // $pan needed for dedup and statutory details
        $pan = !empty($data['pan']) ? strtoupper(trim((string) $data['pan'])) : null;

        // employee_id has never had an enforced format — some records are
        // "EMP202618056", others the bare "202618056" for the same
        // sequence (see Employee::findByEmployeeIdLoose()). A re-uploaded
        // row whose Employee ID is typed in the other form than what's
        // actually stored must still match here, or it silently creates a
        // duplicate employee instead of updating the existing one.
        $numericEmpId    = preg_replace('/^EMP/i', '', $newEmpId);
        $empIdCandidates = array_unique([$newEmpId, $numericEmpId, 'EMP' . $numericEmpId]);

        // ── Dedup step 1: active employee by Emp ID ──────────────────────────
        $employee = Employee::where(function ($q) use ($empIdCandidates) {
                $q->whereIn('employee_id',     $empIdCandidates)
                  ->orWhereIn('external_emp_id', $empIdCandidates);
            })
            ->first();

        // ── Dedup step 2: soft-deleted employee — restore instead of duplicate
        // Check order: Emp ID match → PAN match → Name match
        if (!$employee) {
            $deleted = Employee::onlyTrashed()
                ->where(function ($q) use ($empIdCandidates) {
                    $q->whereIn('employee_id',     $empIdCandidates)
                      ->orWhereIn('external_emp_id', $empIdCandidates);
                })
                ->first();

            // Fallback: match deleted employee by PAN
            if (!$deleted && $pan) {
                $deleted = Employee::onlyTrashed()
                    ->whereHas('statutoryDetails', fn($q) => $q->where('pan_number_hash', hash('sha256', $pan)))
                    ->first();
            }

            // Fallback: match deleted employee by first + last name
            if (!$deleted) {
                $deleted = Employee::onlyTrashed()
                    ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                    ->whereRaw('LOWER(last_name)  = ?', [strtolower($lastName)])
                    ->first();
            }

            if ($deleted) {
                // Restore: clear soft-delete flag (deleted_at = null)
                $deleted->restore();
                // Ensure they show as active immediately (status will be
                // re-set correctly by $fields below, but clear date_of_leaving
                // here in case the import row says "Working")
                $deleted->forceFill(['date_of_leaving' => null])->save();
                $employee = $deleted;
            }
        }

        $fields = [
            'company_id' => $companyId,
            'employee_id'         => $newEmpId,
            'external_emp_id'     => $newEmpId,
            'title'               => $data['title']           ?? null,
            'first_name'          => $firstName,
            'middle_name'         => $middleName,
            'last_name'           => $lastName,
            'gender'              => $gender,
            'date_of_birth'       => $dateOfBirth,
            'marital_status'      => $maritalStatus,
            // NOT NULL column — if the sheet leaves it blank, keep whatever
            // an existing employee already has rather than silently
            // overwriting it, and default a brand-new one to 'Indian'
            // (matches the manual form's default).
            'nationality'         => ($data['nationality'] ?? '') ?: ($employee?->nationality ?? 'Indian'),
            'mobile'              => $mobile,
            'alternate_phone'     => $alternatePhone,
            'official_email'      => $data['official_email']  ?? null,
            'personal_email'      => $data['personal_email']  ?? null,
            'current_address'     => $data['current_address'] ?? null,
            'date_of_joining'     => $dateOfJoining,
            'offer_letter_date'   => $offerLetterDate,
            'employment_type'     => $employmentType,
            'work_type'           => $workType,
            'client_name'         => $workType === 'client_location' ? ($data['client_name'] ?? null) : null,
            'probation_period_months' => $probationMonths,
            'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $emergencyPhone,
            'attendance_group'    => $data['attendance_group'] ?? null,
            'department_id'       => $departmentId,
            'designation_id'      => $designationId,
            'branch_id'           => $branchId,
            'location_id'         => $locationId,
            'current_city'        => $data['current_city']    ?? null,
            'reporting_manager_id'=> $reportingManagerId,
            // If the manager/team-leader can't be resolved yet, keep the raw
            // reference text so it can be backfilled automatically whenever
            // that person is eventually created — see EmployeeObserver /
            // ManagerReferenceResolverService.
            'pending_reporting_manager_ref' => $reportingManagerId ? null : (($data['reporting_manager'] ?? null) ?: null),
            'team_leader_id'      => $teamLeaderId,
            'pending_team_leader_ref'       => $teamLeaderId ? null : (($data['team_leader'] ?? null) ?: null),
            'is_pf_applicable'    => $isPfApplicable,
            'is_esic_applicable'  => $isEsicApplicable,
            'employee_status'     => $status,
            'created_by'          => auth()->id(),
        ];

        $isNewEmployee = !$employee;

        if ($employee) {
            $employee->update($fields);
            $employee->refresh(); // reload to ensure $employee->id is current
        } else {
            $employee = Employee::create($fields);
        }

        // ── Safety guard: ensure employee was actually persisted ─────────────
        // If create() failed for any reason, stop immediately so the transaction
        // rolls back and no bank/statutory records are written for a ghost employee.
        if (!$employee || !$employee->id) {
            throw new \RuntimeException(
                "Row {$rowNumber}: Employee record could not be saved for Emp ID \"{$newEmpId}\". " .
                "Check for duplicate employee_id or database constraint violations."
            );
        }

        // ── Default login account for newly-created employees only ──────────
        // Re-imports that update an existing employee never touch their
        // account/password — only a brand-new row gets one seeded.
        if ($isNewEmployee) {
            (new UserProvisioningService())->createDefaultAccountFor($employee);
        }

        // WFH / Client Location employees have no physical punch device —
        // auto-assign a Bio ID the same way the manual Add/Edit form does
        // (item 64). Runs for updates too, not just new rows, since a bulk
        // re-import can be what actually changes someone's work_type.
        (new EmployeeService())->ensureRemoteBioId($employee);

        // Note: unresolved reporting_manager/team_leader references are
        // persisted onto $employee via pending_reporting_manager_ref /
        // pending_team_leader_ref above, and get backfilled automatically
        // by EmployeeObserver whenever the referenced person is eventually
        // created (via this importer OR the manual "Add Employee" form) —
        // see ManagerReferenceResolverService.

        // ── Statutory details ────────────────────────────────
        $uan = !empty($data['uan_number']) ? trim($data['uan_number']) : null;
        // 'NO UAN' / 'no uan' / 'N/A' → treat as no UAN
        if ($uan !== null && in_array(strtolower($uan), ['no uan', 'n/a', 'na', '-', 'nil', 'none'])) {
            $uan = null;
        }
        $aadhaar  = !empty($data['aadhaar_number']) ? preg_replace('/\D/', '', (string) $data['aadhaar_number']) : null;
        if ($aadhaar !== null && strlen($aadhaar) !== 12) {
            $aadhaar = null; // malformed — silently dropped, same lenient handling as mobile/alternate phone
        }
        $esic     = !empty($data['esic_number']) ? trim($data['esic_number']) : null;
        $pfNumber = !empty($data['pf_number'])   ? trim($data['pf_number'])   : null;

        if ($pan || $uan || $aadhaar || $esic || $pfNumber) {
            EmployeeStatutoryDetail::updateOrCreate(
                ['employee_id' => $employee->id],
                array_filter([
                    'pan_number'     => $pan,
                    'uan_number'     => $uan,
                    'aadhaar_number' => $aadhaar,
                    'esic_number'    => $esic,
                    'pf_number'      => $pfNumber,
                ], fn($v) => $v !== null)
            );
        }

        // ── Bank details ─────────────────────────────────────
        $accountNumber = !empty($data['account_number']) ? trim((string) $data['account_number']) : null;
        $ifscCode      = !empty($data['ifsc_code'])      ? strtoupper(trim($data['ifsc_code']))    : null;

        if ($accountNumber && $ifscCode) {
            // account_number is encrypted (ciphertext varies per row even for the same
            // value), so matching must go through the SHA-256 blind-index column instead.
            EmployeeBankDetail::updateOrCreate(
                ['employee_id' => $employee->id, 'account_number_hash' => hash('sha256', $accountNumber)],
                [
                    'account_number'      => $accountNumber,
                    'bank_name'           => $data['bank_name']    ?? '',
                    'ifsc_code'           => $ifscCode,
                    'account_holder_name' => !empty($data['account_holder_name']) ? $data['account_holder_name'] : $employee->full_name,
                    'account_type'        => 'savings',
                    'is_primary'          => true,
                ]
            );
        }

        // ── Document links ────────────────────────────────────
        // Each *Document Link column carries a OneDrive share link (same 9
        // document types as the manual Add Employee form). Only the link is
        // saved here (file_path stays null) — the actual Graph download
        // happens right after the whole import transaction commits, in
        // WebEmployeeController::import(), not in here. This method runs
        // inside ExcelImportService's all-rows-or-nothing DB transaction, so
        // a slow/failing external HTTP call here could hold that transaction
        // open or leave downloaded files orphaned if a later row rolls
        // everything back. If the post-import fetch ever fails for a row,
        // it stays pending and can be retried with
        // `php artisan documents:fetch-onedrive`.
        $documentLinkColumns = [
            'pan'                => 'pan_doc_link',
            'aadhaar'            => 'aadhaar_doc_link',
            'marksheet'          => 'marksheet_doc_link',
            'reference_letter'   => 'reference_letter_doc_link',
            'experience_letter'  => 'experience_letter_doc_link',
            'cancelled_cheque'   => 'cancelled_cheque_doc_link',
            'uan_document'       => 'uan_document_doc_link',
            'last_salary_slip'   => 'last_salary_slip_doc_link',
            'other'              => 'other_doc_link',
        ];

        foreach ($documentLinkColumns as $documentType => $field) {
            $link = $this->cleanCellString($data[$field] ?? null);
            if (!$link) {
                continue;
            }

            $existing = EmployeeDocument::where('employee_id', $employee->id)
                ->where('document_type', $documentType)
                ->first();

            // Never overwrite an already-downloaded file with a pending link.
            if ($existing && $existing->file_path) {
                continue;
            }

            EmployeeDocument::updateOrCreate(
                ['employee_id' => $employee->id, 'document_type' => $documentType],
                [
                    'document_name' => EmployeeDocument::UPLOAD_TYPES[$documentType] ?? ucfirst($documentType),
                    'source_url'    => $link,
                    'uploaded_by'   => auth()->id(),
                ]
            );
        }
    }

    /**
     * Runs once after every row in the upload has been processed. Sweeps
     * ALL still-unresolved manager/team-leader references (not just ones
     * from this upload) against the current employees table — catches
     * anything EmployeeObserver's live, per-row resolution couldn't, e.g.
     * a manager who already existed from an earlier, unrelated import.
     */
    public function afterImport(): void
    {
        app(\App\Services\ManagerReferenceResolverService::class)->resolveAllPending();
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────


    /**
     * Split a full name into [first, middle, last].
     * "Rajesh Kumar Sharma" → ["Rajesh", "Kumar", "Sharma"]
     * "Rajesh Sharma"       → ["Rajesh", null,    "Sharma"]
     * "Rajesh"              → ["Rajesh", null,    ""]
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) === 0) return ['', null, ''];
        if (count($parts) === 1) return [$parts[0], null, ''];
        if (count($parts) === 2) return [$parts[0], null, $parts[1]];

        $last   = array_pop($parts);
        $first  = array_shift($parts);
        $middle = implode(' ', $parts) ?: null;

        return [$first, $middle, $last];
    }

    // parseDate() provided by ParsesExcelDates trait

    protected function parseBool($value): bool
    {
        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string) $value)), ['yes', 'true', '1', 'y', 'applicable']);
    }

    /** Excel dropdown label -> employees.employment_type enum value. Unrecognised/blank -> full_time, matching the manual form's default. */
    protected function mapEmploymentType($raw): string
    {
        $map = [
            'full time' => 'full_time', 'full_time' => 'full_time',
            'part time' => 'part_time', 'part_time' => 'part_time',
            'contract'  => 'contract',
            'intern'    => 'intern',
            'consultant'=> 'consultant',
            'retainer'  => 'retainer',
        ];
        return $map[strtolower(trim((string) $raw))] ?? 'full_time';
    }

    /** Excel dropdown label -> employees.work_type enum value. Unrecognised/blank -> wfo, matching the manual form's default. */
    protected function mapWorkType($raw): string
    {
        $map = [
            'work from office' => 'wfo', 'wfo' => 'wfo',
            'work from home'   => 'wfh', 'wfh' => 'wfh',
            'hybrid'           => 'hybrid',
            'client location'  => 'client_location', 'client_location' => 'client_location',
        ];
        return $map[strtolower(trim((string) $raw))] ?? 'wfo';
    }

    /** Excel dropdown label -> employees.marital_status stored value. Unrecognised/blank -> null (optional field, no default). */
    protected function mapMaritalStatus($raw): ?string
    {
        $map = [
            'single' => 'single', 'married' => 'married',
            'divorced' => 'divorced', 'widowed' => 'widowed',
        ];
        return $map[strtolower(trim((string) $raw))] ?? null;
    }

    protected function normaliseStatus(string $raw): string
    {
        // Must match employees.employee_status enum exactly:
        // 'active','inactive','on_notice','absconding','suspended','on_leave'
        $map = [
            'active'      => 'active',
            'inactive'    => 'inactive',
            'left'        => 'inactive',
            'resigned'    => 'inactive',
            'terminated'  => 'inactive',
            'retired'     => 'inactive',
            'notice'      => 'on_notice',
            'on notice'   => 'on_notice',
            'on_notice'   => 'on_notice',
            'absconded'   => 'absconding',
            'absconding'  => 'absconding',
            'suspended'   => 'suspended',
            'on leave'    => 'on_leave',
            'on_leave'    => 'on_leave',
        ];
        return $map[strtolower(trim($raw))] ?? 'active';
    }

    /**
     * Look up an employee within the same entity by employee_id/external_emp_id or by full name.
     */
    protected function resolveEmployee(int $companyId, $value): ?int
    {
        $value = $this->cleanCellString($value);
        if ($value === null) return null;

        // Try as employee ID first — tolerant of an inconsistent "EMP"
        // prefix (see Employee::findByEmployeeIdLoose()); external_emp_id
        // is a separate id namespace, matched exactly.
        $numericValue = preg_replace('/^EMP/i', '', $value);
        $id = Employee::where('company_id', $companyId)
            ->where(function ($q) use ($value, $numericValue) {
                $q->whereIn('employee_id', array_unique([$value, $numericValue, 'EMP' . $numericValue]))
                  ->orWhere('external_emp_id', $value);
            })
            ->value('id');

        if ($id) return $id;

        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/i', $value)) {
            $id = Employee::where('company_id', $companyId)
                ->whereHas('statutoryDetails', fn($q) => $q->where('pan_number_hash', hash('sha256', strtoupper($value))))
                ->value('id');

            if ($id) return $id;
        }

        // Fallback: match by full name (case-insensitive)
        return Employee::where('company_id', $companyId)
            ->whereRaw("TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(middle_name,''),' ',COALESCE(last_name,''))) = ?", [trim($value)])
            ->value('id');
    }

    protected function resolveImportCompanyId($firm): int
    {
        $defaultCompanyId = (int) ($this->meta['company_id'] ?? Company::query()->value('id') ?? 1);
        $firm = $this->cleanCellString($firm);

        if ($firm === null) {
            return $defaultCompanyId;
        }

        $company = Company::firstOrCreate(
            ['name' => $firm],
            ['short_name' => $this->makeShortName($firm), 'is_active' => true]
        );

        return (int) $company->id;
    }

    protected function resolveOrCreateMasterId(string $model, $value, int $companyId, array $extra = []): ?int
    {
        $name = $this->cleanCellString($value);
        if ($name === null) return null;

        $record = $model::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = LOWER(?)', [$name])
            ->first();

        if ($record) {
            return (int) $record->id;
        }

        $payload = array_filter(array_merge([
            'company_id' => $companyId,
            'name' => $name,
            'code' => $this->makeCode($name),
            'is_active' => true,
        ], $extra), fn($v) => $v !== null && $v !== '');

        return (int) $model::create($payload)->id;
    }

    protected function cleanCellString($value): ?string
    {
        if ($value === null) return null;

        $value = trim((string) $value);
        if ($value === '') return null;

        return preg_replace('/\s+/', ' ', $value);
    }

    protected function makeCode(string $name): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $name));
        return substr($code ?: 'AUTO', 0, 20);
    }

    protected function makeShortName(string $name): string
    {
        $words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $short = count($words) > 1
            ? implode('', array_map(fn($word) => strtoupper($word[0]), $words))
            : strtoupper(substr($name, 0, 8));

        return substr($short ?: 'FIRM', 0, 50);
    }
}
