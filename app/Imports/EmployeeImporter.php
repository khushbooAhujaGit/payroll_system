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
use App\Services\EmployeeService;
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
    protected array $pendingEmployeeLinks = [];

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

    

    public function getTemplateHeaders(): array
    {
        return [
            'New Emp ID', 'Employee Name', 'Left Date',
            'Reporting Manager', 'Team Leader', 'Attendance', 'Mobile Number',
            'PAN', 'Marital Status', 'Gender', 'Birth Date', 'Join Date',
            'Department', 'Designation Final', 'Location (City)',
            'UAN No (if applicable)',
            'Bank Name', 'Account Number', 'IFSC Code', 'Email address',
            'PF', 'RSC Mail ID', 'Firm', 'PF Applicability', 'UAN Number',
            'Offer Letter Date', 'Left Type',
        ];
    }


    public function getSampleRows(): array
    {
        return []; // empty template — headers only
    }


    protected function getRequiredHeaders(): array
    {
        return ['New Emp ID', 'Employee Name', 'Join Date', 'Bank Name', 'Account Number', 'IFSC Code'];
    }

    

    public function getColumnValidations(): array
    {
        return [
            'New Emp ID'      => ['required' => true,  'format' => 'Your company employee ID', 'example' => '201820001'],
            'Employee Name'   => ['required' => true,  'format' => 'Full name (split into first/middle/last)', 'example' => 'Rajesh Kumar Sharma'],
            'Join Date'       => ['required' => true,  'format' => 'DD-MM-YYYY', 'example' => '01-01-2024'],
            'Title'           => ['required' => false, 'format' => 'Mr / Mrs / Ms / Dr / Prof', 'example' => 'Mr'],
            'Gender'          => ['required' => false, 'format' => 'Male / Female / Other', 'example' => 'Male'],
            'Birth Date'      => ['required' => false, 'format' => 'DD-MM-YYYY', 'example' => '15-06-1990'],
            'Mobile Number'   => ['required' => false, 'format' => '10 digit number', 'example' => '9876543210'],
            'PAN'             => ['required' => false, 'format' => '10 char alphanumeric (ABCDE1234F)', 'example' => 'ABCDE1234F'],
            'ESI Number (if applicable)' => ['required' => false, 'format' => 'ESIC Number', 'example' => 'ESI1234'],
            'Bank Name'       => ['required' => true,  'format' => 'Bank name', 'example' => 'HDFC Bank'],
            'Account Number'  => ['required' => true,  'format' => 'Numeric', 'example' => '50100123456789'],
            'IFSC Code'       => ['required' => true,  'format' => '11 char (HDFC0001234)', 'example' => 'HDFC0001234'],
            'PF Applicability'=> ['required' => false, 'format' => 'Yes / No', 'example' => 'Yes'],
            'UAN Number'      => ['required' => false, 'format' => '12 digit number', 'example' => '101234567890'],
            'Offer Letter Date' => ['required' => false, 'format' => 'DD-MM-YYYY', 'example' => '01-12-2023'],
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
            'New Emp ID'                                                     => 'new_emp_id',
            'Count'                                                          => '_skip_count',
            'Employee Name'                                                  => 'employee_name',
            'CurrentStatus'                                                  => 'current_status',
            'Left Date'                                                      => 'left_date',
            'Reporting Manager'                                              => 'reporting_manager',
            'Team Leader'                                                    => 'team_leader',
            'Attendance'                                                     => 'attendance_group',
            'Office'                                                         => 'branch',
            'Previouse UAN Track'                                            => '_skip_uan_track',
            'Mobile Number'                                                  => 'mobile',
            'PAN'                                                            => 'pan',
            'Marital Status'                                                 => 'marital_status',
            'Gender'                                                         => 'gender',
            'Birth Date'                                                     => 'date_of_birth',
            'Join Date'                                                      => 'date_of_joining',
            'Department'                                                     => 'department',
            'Designation Final'                                              => 'designation',
            'Location (City)'                                                => 'current_city',
            'PF Number (if applicable)'                                      => 'pf_number',
            'UAN No (if applicable)'                                         => '_skip_uan_old',
            'ESI Number (if applicable)'                                     => 'esic_number',
            'Bank Name'                                                      => 'bank_name',
            'Account Number'                                                 => 'account_number',
            'IFSC Code'                                                      => 'ifsc_code',
            'Email address'                                                  => 'official_email',
            'Please attach a copy of your PAN card (size limit 10 MB)'      => 'pan_doc_path',
            'Please attach a copy of your AADHAAR card (size limit 10 MB)'  => 'aadhaar_doc_path',
            'PF'                                                             => '_skip_pf_old',
            'RSC Mail ID'                                                    => 'personal_email',
            'Firm'                                                           => 'firm',
            'PF Applicability'                                               => 'pf_applicability',
            'UAN Number'                                                     => 'uan_number',
            'Offer Letter Date'                                              => 'offer_letter_date',
            'Left Type'                                                      => 'left_type',
        ];

        return $map[$header] ?? '_skip_' . strtolower(str_replace([' ', '(', ')'], '_', $header));
    }

    // ─────────────────────────────────────────────
    // VALIDATION
    // ─────────────────────────────────────────────

    public function validate(array $data, int $rowNumber): array
    {
        $errors   = [];
        $newEmpId = trim((string) ($data['new_emp_id'] ?? ''));

        // ── Required: New Emp ID ─────────────────────────────────────────────
        if ($newEmpId === '') {
            $errors[] = "New Emp ID is required.";
        } else {
            // Detect duplicate employee IDs within the same upload file
            if (isset($this->seenEmpIds[$newEmpId])) {
                $errors[] = "Duplicate Emp ID \"{$newEmpId}\" — already present in row {$this->seenEmpIds[$newEmpId]} of this file.";
            } else {
                $this->seenEmpIds[$newEmpId] = $rowNumber;
            }
        }

        // ── Required: Employee Name ──────────────────────────────────────────
        if (empty($data['employee_name'])) {
            $errors[] = "Employee Name is required.";
        }

        // ── Required: Join Date ──────────────────────────────────────────────
        if (empty($data['date_of_joining'])) {
            $errors[] = "Join Date is required.";
        } elseif ($this->parseDate($data['date_of_joining']) === null) {
            $errors[] = "Join Date could not be parsed. Got: \"{$data['date_of_joining']}\". Use DD-MM-YYYY.";
        }

        // ── Optional: PAN — check for duplicates within the file and in the DB ──
        $pan = !empty($data['pan']) ? strtoupper(trim((string) $data['pan'])) : null;
        if ($pan && $newEmpId !== '') {
            if (isset($this->seenPans[$pan])) {
                $errors[] = "Duplicate PAN \"{$pan}\" — already present in row {$this->seenPans[$pan]} of this file. "
                          . "Each employee must have a unique PAN.";
            } else {
                $this->seenPans[$pan] = $rowNumber;

                // Check if this PAN is already registered to a DIFFERENT employee_id in the DB
                $existingWithPan = Employee::whereHas(
                    'statutoryDetails',
                    fn($q) => $q->where('pan_number', $pan)
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

        // ── Required: Bank Details ───────────────────────────────────────────
        if (empty($data['bank_name'])) {
            $errors[] = "Bank Name is required.";
        }
        if (empty($data['account_number'])) {
            $errors[] = "Account Number is required.";
        }
        if (empty($data['ifsc_code'])) {
            $errors[] = "IFSC Code is required.";
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
            throw new \RuntimeException("Row {$rowNumber}: New Emp ID is empty.");
        }

        // ── Split employee name ──────────────────────────────
        [$firstName, $middleName, $lastName] = $this->splitName($data['employee_name'] ?? '');

        if ($firstName === '') {
            throw new \RuntimeException("Row {$rowNumber}: Could not parse Employee Name.");
        }

        // ── Parse dates ──────────────────────────────────────
        $dateOfJoining   = $this->parseDate($data['date_of_joining'] ?? '');
        // dd($dateOfJoining, $data);
        $dateOfBirth     = $this->parseDate($data['date_of_birth'] ?? '');   // null when non-date value like a name
        $offerLetterDate = $this->parseDate($data['offer_letter_date'] ?? ''); // null when blank or unparseable

        if ($dateOfJoining === null) {
            throw new \RuntimeException("Row {$rowNumber}: Join Date is missing or invalid.");
        }

        // ── Booleans ─────────────────────────────────────────
        $isPfApplicable   = $this->parseBool($data['pf_applicability'] ?? 'No');
        $isEsicApplicable = !empty($data['esic_number']);

        // ── Normalise status ─────────────────────────────────
        $status = $this->normaliseStatus($data['current_status'] ?? 'active');

        // ── Override status using Left Date ───────────────────
        // Left Date can arrive as:
        //   • "Working" / empty           → still employed, skip
        //   • "31-10-2024" (DD-MM-YYYY)   → text string from Excel text-formatted cell
        //   • 45473 / 45473.0             → Excel date serial (cell stored as number)
        //   • DateTimeInterface object    → PhpSpreadsheet parsed a date-formatted cell
        // Pass the raw value directly to parseDate() so it handles all three types.
        $leftDateRaw   = $data['left_date'] ?? null;
        $dateOfLeaving = null;
        // Quick guard: if it's a non-date string like "Working", skip entirely
        $leftDateStr = is_string($leftDateRaw) ? strtolower(trim($leftDateRaw)) : '';
        if ($leftDateRaw !== null && $leftDateStr !== '' && $leftDateStr !== 'working') {
            $dateOfLeaving = $this->parseDate($leftDateRaw);
            if ($dateOfLeaving !== null) {
                $status = 'inactive'; // resigned / terminated — both stored as inactive
            }
        }

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

        $reportingManagerId = $this->resolveEmployee($companyId, $data['reporting_manager'] ?? null);
        $teamLeaderId       = $this->resolveEmployee($companyId, $data['team_leader']       ?? null);

        // $pan needed for dedup and statutory details
        $pan = !empty($data['pan']) ? strtoupper(trim((string) $data['pan'])) : null;

        // ── Dedup step 1: active employee by Emp ID ──────────────────────────
        $employee = Employee::where(function ($q) use ($newEmpId) {
                $q->where('employee_id',     $newEmpId)
                  ->orWhere('external_emp_id', $newEmpId);
            })
            ->first();

        // ── Dedup step 2: soft-deleted employee — restore instead of duplicate
        // Check order: Emp ID match → PAN match → Name match
        if (!$employee) {
            $deleted = Employee::onlyTrashed()
                ->where(function ($q) use ($newEmpId) {
                    $q->where('employee_id',     $newEmpId)
                      ->orWhere('external_emp_id', $newEmpId);
                })
                ->first();

            // Fallback: match deleted employee by PAN
            if (!$deleted && $pan) {
                $deleted = Employee::onlyTrashed()
                    ->whereHas('statutoryDetails', fn($q) => $q->where('pan_number', $pan))
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
            'gender'              => $data['gender']          ?? null,
            'date_of_birth'       => $dateOfBirth,
            'marital_status'      => $data['marital_status']  ?? null,
            'mobile'              => $mobile,
            'official_email'      => $data['official_email']  ?? null,
            'personal_email'      => $data['personal_email']  ?? null,
            'date_of_joining'     => $dateOfJoining,
            'date_of_leaving'     => $dateOfLeaving,
            'offer_letter_date'   => $offerLetterDate,
            'employment_type'     => 'full_time',
            'work_type'           => 'wfo',
            'attendance_group'    => $data['attendance_group'] ?? null,
            'department_id'       => $departmentId,
            'designation_id'      => $designationId,
            'branch_id'           => $branchId,
            'current_city'        => $data['current_city']    ?? null,
            'reporting_manager_id'=> $reportingManagerId,
            'team_leader_id'      => $teamLeaderId,
            'is_pf_applicable'    => $isPfApplicable,
            'is_esic_applicable'  => $isEsicApplicable,
            'employee_status'     => $status,
            'created_by'          => auth()->id(),
        ];

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

        if (!$reportingManagerId && !empty($data['reporting_manager'])) {
            $this->pendingEmployeeLinks[] = [
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'field' => 'reporting_manager_id',
                'reference' => $data['reporting_manager'],
            ];
        }

        if (!$teamLeaderId && !empty($data['team_leader'])) {
            $this->pendingEmployeeLinks[] = [
                'employee_id' => $employee->id,
                'company_id' => $companyId,
                'field' => 'team_leader_id',
                'reference' => $data['team_leader'],
            ];
        }

        // ── Statutory details ────────────────────────────────
        $uan = !empty($data['uan_number']) ? trim($data['uan_number']) : null;
        // 'NO UAN' / 'no uan' / 'N/A' → treat as no UAN
        if ($uan !== null && in_array(strtolower($uan), ['no uan', 'n/a', 'na', '-', 'nil', 'none'])) {
            $uan = null;
        }
        $esic     = !empty($data['esic_number']) ? trim($data['esic_number']) : null;
        $pfNumber = !empty($data['pf_number'])   ? trim($data['pf_number'])   : null;

        if ($pan || $uan || $esic || $pfNumber) {
            EmployeeStatutoryDetail::updateOrCreate(
                ['employee_id' => $employee->id],
                array_filter([
                    'pan_number'  => $pan,
                    'uan_number'  => $uan,
                    'esic_number' => $esic,
                    'pf_number'   => $pfNumber,
                ], fn($v) => $v !== null)
            );
        }

        // ── Bank details ─────────────────────────────────────
        $accountNumber = !empty($data['account_number']) ? trim((string) $data['account_number']) : null;
        $ifscCode      = !empty($data['ifsc_code'])      ? strtoupper(trim($data['ifsc_code']))    : null;

        if ($accountNumber && $ifscCode) {
            EmployeeBankDetail::updateOrCreate(
                ['employee_id' => $employee->id, 'account_number' => $accountNumber],
                [
                    'bank_name'           => $data['bank_name']    ?? '',
                    'ifsc_code'           => $ifscCode,
                    'account_holder_name' => $employee->full_name,
                    'account_type'        => 'savings',
                    'is_primary'          => true,
                ]
            );
        }

        // ── Document links (PAN card / Aadhaar card) ──────────
        // Cells contain URLs (e.g. Google Drive links) submitted via form upload.
        // Stored as EmployeeDocument records so the link is traceable per employee.
        $panDocPath     = $this->cleanCellString($data['pan_doc_path']     ?? null);
        $aadhaarDocPath = $this->cleanCellString($data['aadhaar_doc_path'] ?? null);

        if ($panDocPath) {
            EmployeeDocument::updateOrCreate(
                ['employee_id' => $employee->id, 'document_type' => 'pan'],
                [
                    'document_name' => 'PAN Card',
                    'file_path'     => $panDocPath,
                    'file_name'     => basename($panDocPath),
                    'uploaded_by'   => auth()->id(),
                ]
            );
        }

        if ($aadhaarDocPath) {
            // Also write to the denormalised column on the employee row
            $employee->update(['aadhaar_document_path' => $aadhaarDocPath]);

            EmployeeDocument::updateOrCreate(
                ['employee_id' => $employee->id, 'document_type' => 'aadhaar'],
                [
                    'document_name' => 'Aadhaar Card',
                    'file_path'     => $aadhaarDocPath,
                    'file_name'     => basename($aadhaarDocPath),
                    'uploaded_by'   => auth()->id(),
                ]
            );
        }
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    public function afterImport(): void
    {
        foreach ($this->pendingEmployeeLinks as $link) {
            $managerId = $this->resolveEmployee($link['company_id'], $link['reference']);

            if (!$managerId || $managerId === $link['employee_id']) {
                continue;
            }

            Employee::whereKey($link['employee_id'])->update([
                $link['field'] => $managerId,
            ]);
        }
    }

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

        // Try as employee ID first
        $id = Employee::where('company_id', $companyId)
            ->where(function ($q) use ($value) {
                $q->where('employee_id',     $value)
                  ->orWhere('external_emp_id', $value);
            })
            ->value('id');

        if ($id) return $id;

        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/i', $value)) {
            $id = Employee::where('company_id', $companyId)
                ->whereHas('statutoryDetails', fn($q) => $q->where('pan_number', strtoupper($value)))
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
