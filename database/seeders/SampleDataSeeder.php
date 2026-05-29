<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Entity;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Grade;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\LeaveType;
use App\Models\LeavePolicy;
use App\Models\PfSetting;
use App\Models\EsicSetting;
use App\Models\PtSlab;
use App\Models\LwfSlab;
use App\Models\HolidayCalendar;
use App\Models\Holiday;
use App\Models\LoanType;
use App\Models\ReimbursementType;
use App\Models\HelpdeskCategory;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Company & Entity ──────────────────────────────────────
        $company = Company::firstOrCreate(['name' => 'Acme Technologies Pvt Ltd'], [
            'short_name' => 'Acme',
            'email'      => 'hr@acme.com',
            'pan'        => 'AABCA1234C',
            'is_active'  => true,
        ]);

        $entity = Entity::firstOrCreate(['company_id' => $company->id, 'name' => 'Acme Technologies Pvt Ltd'], [
            'short_name'        => 'ATPL',
            'pan'               => 'AABCA1234C',
            'pf_number'         => 'MH/BAN/12345',
            'esic_number'       => 'MH12345',
            'state'             => 'Maharashtra',
            'payroll_cycle'     => 'monthly',
            'payroll_cutoff_day'=> 31,
            'is_active'         => true,
        ]);

        // ── Branches ─────────────────────────────────────────────
        $branches = [
            ['name' => 'Mumbai HO', 'code' => 'MUM', 'city' => 'Mumbai', 'state' => 'Maharashtra'],
            ['name' => 'Pune Office', 'code' => 'PUN', 'city' => 'Pune',  'state' => 'Maharashtra'],
            ['name' => 'Bangalore Office', 'code' => 'BLR', 'city' => 'Bangalore', 'state' => 'Karnataka'],
        ];
        foreach ($branches as $b) {
            Branch::firstOrCreate(['entity_id' => $entity->id, 'name' => $b['name']], array_merge($b, ['entity_id' => $entity->id]));
        }

        // ── Departments ───────────────────────────────────────────
        $departments = ['Engineering', 'Human Resources', 'Finance & Accounts', 'Sales', 'Marketing', 'Operations', 'IT Infrastructure', 'Product', 'Legal & Compliance'];
        foreach ($departments as $d) {
            Department::firstOrCreate(['entity_id' => $entity->id, 'name' => $d], ['entity_id' => $entity->id, 'code' => strtoupper(substr($d, 0, 3))]);
        }

        // ── Designations ─────────────────────────────────────────
        $designations = [
            'Software Engineer', 'Senior Software Engineer', 'Lead Engineer', 'Principal Engineer', 'Engineering Manager',
            'HR Executive', 'HR Manager', 'HR Business Partner',
            'Accountant', 'Senior Accountant', 'Finance Manager', 'CFO',
            'Sales Executive', 'Sales Manager', 'VP Sales',
            'Product Manager', 'Senior Product Manager', 'VP Product',
            'CEO', 'COO', 'CTO',
        ];
        foreach ($designations as $d) {
            Designation::firstOrCreate(['entity_id' => $entity->id, 'name' => $d], ['entity_id' => $entity->id]);
        }

        // ── Grades ───────────────────────────────────────────────
        $grades = [
            ['name' => 'Grade L1', 'code' => 'L1', 'level' => 1, 'min_ctc' => 300000,  'max_ctc' => 600000],
            ['name' => 'Grade L2', 'code' => 'L2', 'level' => 2, 'min_ctc' => 600001,  'max_ctc' => 1000000],
            ['name' => 'Grade L3', 'code' => 'L3', 'level' => 3, 'min_ctc' => 1000001, 'max_ctc' => 1500000],
            ['name' => 'Grade L4', 'code' => 'L4', 'level' => 4, 'min_ctc' => 1500001, 'max_ctc' => 2500000],
            ['name' => 'Grade L5', 'code' => 'L5', 'level' => 5, 'min_ctc' => 2500001, 'max_ctc' => 5000000],
            ['name' => 'Grade L6', 'code' => 'L6', 'level' => 6, 'min_ctc' => 5000001, 'max_ctc' => null],
        ];
        foreach ($grades as $g) {
            Grade::firstOrCreate(['entity_id' => $entity->id, 'code' => $g['code']], array_merge($g, ['entity_id' => $entity->id]));
        }

        // ── Salary Components ─────────────────────────────────────
        $components = [
            ['name' => 'Basic Salary',          'code' => 'BASIC',      'type' => 'earning',  'calc_type' => 'percentage', 'pct_of' => 'GROSS',  'pct_val' => 40,   'pf' => true,  'sort' => 1],
            ['name' => 'House Rent Allowance',   'code' => 'HRA',        'type' => 'earning',  'calc_type' => 'percentage', 'pct_of' => 'BASIC',  'pct_val' => 50,   'pf' => false, 'sort' => 2],
            ['name' => 'Dearness Allowance',     'code' => 'DA',         'type' => 'earning',  'calc_type' => 'fixed',      'pct_of' => null,     'pct_val' => null, 'pf' => true,  'sort' => 3],
            ['name' => 'Special Allowance',      'code' => 'SPEC_ALLOW', 'type' => 'earning',  'calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 4],
            ['name' => 'Transport Allowance',    'code' => 'TRANS',      'type' => 'earning',  'calc_type' => 'fixed',      'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 5],
            ['name' => 'Medical Allowance',      'code' => 'MED',        'type' => 'earning',  'calc_type' => 'fixed',      'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 6],
            ['name' => 'Provident Fund (Emp)',   'code' => 'PF_EMP',     'type' => 'deduction','calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 10],
            ['name' => 'ESIC (Employee)',         'code' => 'ESIC_EMP',   'type' => 'deduction','calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 11],
            ['name' => 'Professional Tax',        'code' => 'PT',         'type' => 'deduction','calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 12],
            ['name' => 'TDS / Income Tax',        'code' => 'TDS',        'type' => 'deduction','calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 13],
            ['name' => 'Loan Deduction',          'code' => 'LOAN',       'type' => 'deduction','calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 14],
            ['name' => 'LWF (Employee)',           'code' => 'LWF_EMP',    'type' => 'deduction','calc_type' => 'formula',    'pct_of' => null,     'pct_val' => null, 'pf' => false, 'sort' => 15],
            ['name' => 'PF Employer Contribution','code' => 'PF_EMP_ER',  'type' => 'employer_contribution','calc_type' => 'formula', 'pct_of' => null, 'pct_val' => null, 'pf' => false, 'sort' => 20],
            ['name' => 'ESIC Employer',           'code' => 'ESIC_EMP_ER','type' => 'employer_contribution','calc_type' => 'formula', 'pct_of' => null, 'pct_val' => null, 'pf' => false, 'sort' => 21],
            ['name' => 'LWF Employer',            'code' => 'LWF_EMP_ER', 'type' => 'employer_contribution','calc_type' => 'formula', 'pct_of' => null, 'pct_val' => null, 'pf' => false, 'sort' => 22],
        ];

        $componentMap = [];
        foreach ($components as $c) {
            $comp = SalaryComponent::firstOrCreate(['code' => $c['code']], [
                'entity_id'         => $entity->id,
                'name'              => $c['name'],
                'component_type'    => $c['type'],
                'calculation_type'  => $c['calc_type'],
                'percentage_of'     => $c['pct_of'] ?? null,
                'percentage_value'  => $c['pct_val'] ?? null,
                'is_taxable'        => !in_array($c['code'], ['PF_EMP', 'PF_EMP_ER', 'ESIC_EMP', 'ESIC_EMP_ER', 'PT', 'LWF_EMP', 'LWF_EMP_ER', 'LOAN']),
                'is_pf_applicable'  => $c['pf'],
                'is_in_ctc'         => !in_array($c['type'], ['deduction']),
                'is_in_gross'       => $c['type'] === 'earning',
                'show_in_payslip'   => true,
                'sort_order'        => $c['sort'],
            ]);
            $componentMap[$c['code']] = $comp->id;
        }

        // ── Salary Structure ─────────────────────────────────────
        $structure = SalaryStructure::firstOrCreate(['entity_id' => $entity->id, 'name' => 'Standard Monthly'], ['is_active' => true]);

        $structureComponents = [
            ['code' => 'BASIC',     'calc' => 'percentage', 'pct_of' => 'GROSS', 'pct_val' => 40],
            ['code' => 'HRA',       'calc' => 'percentage', 'pct_of' => 'BASIC', 'pct_val' => 50],
            ['code' => 'TRANS',     'calc' => 'fixed',      'fixed'  => 1600],
            ['code' => 'MED',       'calc' => 'fixed',      'fixed'  => 1250],
        ];
        foreach ($structureComponents as $idx => $sc) {
            if (!isset($componentMap[$sc['code']])) continue;
            SalaryStructureComponent::firstOrCreate(
                ['salary_structure_id' => $structure->id, 'salary_component_id' => $componentMap[$sc['code']]],
                [
                    'calculation_override' => $sc['calc'],
                    'fixed_value'          => $sc['fixed'] ?? null,
                    'percentage_value'     => $sc['pct_val'] ?? null,
                    'percentage_of'        => $sc['pct_of'] ?? null,
                    'sort_order'           => $idx,
                ]
            );
        }

        // ── Statutory Settings ────────────────────────────────────
        PfSetting::firstOrCreate(['entity_id' => $entity->id], [
            'employee_contribution_pct' => 12.00,
            'employer_contribution_pct' => 12.00,
            'eps_contribution_pct'      => 8.33,
            'edli_contribution_pct'     => 0.50,
            'admin_charges_pct'         => 0.50,
            'cap_at_wage_limit'         => true,
            'wage_limit'                => 15000.00,
            'allow_vpf'                 => true,
        ]);

        EsicSetting::firstOrCreate(['entity_id' => $entity->id], [
            'employee_contribution_pct' => 0.75,
            'employer_contribution_pct' => 3.25,
            'wage_limit'                => 21000.00,
        ]);

        // Maharashtra PT slabs
        $ptSlabs = [
            ['from' => 0,      'to' => 7500,  'pt' => 0],
            ['from' => 7501,   'to' => 10000, 'pt' => 175],
            ['from' => 10001,  'to' => null,  'pt' => 200],  // 300 in Feb
        ];
        foreach ($ptSlabs as $slab) {
            PtSlab::firstOrCreate(['entity_id' => $entity->id, 'salary_from' => $slab['from']], [
                'state'      => 'Maharashtra',
                'salary_from'=> $slab['from'],
                'salary_to'  => $slab['to'],
                'pt_amount'  => $slab['pt'],
                'is_active'  => true,
            ]);
        }

        // LWF - Maharashtra
        LwfSlab::firstOrCreate(['entity_id' => $entity->id, 'state' => 'Maharashtra'], [
            'employee_contribution' => 6,
            'employer_contribution' => 12,
            'frequency'             => 'half_yearly',
            'deduction_month'       => 'june_december',
            'is_active'             => true,
        ]);

        // ── Leave Types ───────────────────────────────────────────
        $leaveTypes = [
            ['name' => 'Casual Leave',       'code' => 'CL',  'category' => 'casual',     'is_paid' => true,  'max_cf' => 0,   'days' => 12],
            ['name' => 'Sick Leave',          'code' => 'SL',  'category' => 'sick',       'is_paid' => true,  'max_cf' => 0,   'days' => 12],
            ['name' => 'Earned Leave',        'code' => 'EL',  'category' => 'earned',     'is_paid' => true,  'max_cf' => 30,  'days' => 15, 'is_cf' => true, 'is_enc' => true],
            ['name' => 'Maternity Leave',     'code' => 'ML',  'category' => 'maternity',  'is_paid' => true,  'max_cf' => 0,   'days' => 182, 'gender' => 'female'],
            ['name' => 'Paternity Leave',     'code' => 'PL',  'category' => 'paternity',  'is_paid' => true,  'max_cf' => 0,   'days' => 15,  'gender' => 'male'],
            ['name' => 'Comp Off',            'code' => 'CO',  'category' => 'comp_off',   'is_paid' => true,  'max_cf' => 0,   'days' => 0],
            ['name' => 'Loss of Pay',         'code' => 'LOP', 'category' => 'lop',        'is_paid' => false, 'max_cf' => 0,   'days' => 0,  'is_lop' => true],
            ['name' => 'Bereavement Leave',   'code' => 'BL',  'category' => 'bereavement','is_paid' => true,  'max_cf' => 0,   'days' => 3],
        ];

        foreach ($leaveTypes as $lt) {
            $type = LeaveType::firstOrCreate(['entity_id' => $entity->id, 'code' => $lt['code']], [
                'name'             => $lt['name'],
                'category'         => $lt['category'],
                'is_paid'          => $lt['is_paid'],
                'is_carry_forward' => $lt['is_cf'] ?? false,
                'max_carry_forward'=> $lt['max_cf'],
                'is_encashable'    => $lt['is_enc'] ?? false,
                'is_lop_type'      => $lt['is_lop'] ?? false,
                'gender_specific'  => $lt['gender'] ?? 'all',
                'is_active'        => true,
                'color'            => $this->leaveColor($lt['code']),
            ]);

            if ($lt['days'] > 0) {
                LeavePolicy::firstOrCreate(['entity_id' => $entity->id, 'leave_type_id' => $type->id], [
                    'name'          => $lt['name'] . ' Policy',
                    'days_per_year' => $lt['days'],
                    'accrual_type'  => 'annual',
                    'is_active'     => true,
                ]);
            }
        }

        // ── Loan Types ────────────────────────────────────────────
        $loanTypes = [
            ['name' => 'Salary Advance',  'code' => 'SA',    'max_amount' => 50000,  'max_emi' => 3,   'rate' => 0],
            ['name' => 'Personal Loan',   'code' => 'PL',    'max_amount' => 200000, 'max_emi' => 24,  'rate' => 12],
            ['name' => 'Festival Advance','code' => 'FA',    'max_amount' => 20000,  'max_emi' => 2,   'rate' => 0],
            ['name' => 'Medical Advance', 'code' => 'MA',    'max_amount' => 100000, 'max_emi' => 6,   'rate' => 0],
        ];
        foreach ($loanTypes as $lt) {
            \App\Models\LoanType::firstOrCreate(['entity_id' => $entity->id, 'code' => $lt['code']], [
                'name'          => $lt['name'],
                'max_amount'    => $lt['max_amount'],
                'max_emi'       => $lt['max_emi'],
                'interest_rate' => $lt['rate'],
                'is_active'     => true,
            ]);
        }

        // ── Reimbursement Types ───────────────────────────────────
        $rTypes = [
            ['name' => 'Travel Reimbursement', 'code' => 'TRAVEL', 'limit' => 5000,  'taxable' => false],
            ['name' => 'Fuel Allowance',        'code' => 'FUEL',   'limit' => 3000,  'taxable' => false],
            ['name' => 'Mobile Allowance',      'code' => 'MOBILE', 'limit' => 1000,  'taxable' => false],
            ['name' => 'Food Allowance',        'code' => 'FOOD',   'limit' => 2000,  'taxable' => false],
            ['name' => 'Internet Allowance',    'code' => 'INET',   'limit' => 1500,  'taxable' => false],
            ['name' => 'Uniform Allowance',     'code' => 'UNIF',   'limit' => 5000,  'taxable' => false],
        ];
        foreach ($rTypes as $rt) {
            \App\Models\ReimbursementType::firstOrCreate(['entity_id' => $entity->id, 'code' => $rt['code']], [
                'name'          => $rt['name'],
                'monthly_limit' => $rt['limit'],
                'is_taxable'    => $rt['taxable'],
                'requires_bill' => true,
                'is_active'     => true,
            ]);
        }

        // ── Helpdesk Categories ───────────────────────────────────
        $categories = [
            ['name' => 'Payroll Query',      'team' => 'payroll', 'sla' => 24],
            ['name' => 'Leave Issue',         'team' => 'hr',      'sla' => 24],
            ['name' => 'IT Support',          'team' => 'it',      'sla' => 8],
            ['name' => 'HR General Query',    'team' => 'hr',      'sla' => 48],
            ['name' => 'Salary Slip Request', 'team' => 'payroll', 'sla' => 4],
            ['name' => 'Reimbursement Query', 'team' => 'finance', 'sla' => 48],
            ['name' => 'Loan Query',          'team' => 'finance', 'sla' => 24],
            ['name' => 'Attendance Issue',    'team' => 'hr',      'sla' => 12],
        ];
        foreach ($categories as $cat) {
            \App\Models\HelpdeskCategory::firstOrCreate(['entity_id' => $entity->id, 'name' => $cat['name']], [
                'assigned_team' => $cat['team'],
                'sla_hours'     => $cat['sla'],
                'is_active'     => true,
            ]);
        }

        // ── Holiday Calendar ──────────────────────────────────────
        $calendar = HolidayCalendar::firstOrCreate(['entity_id' => $entity->id, 'year' => 2025], [
            'name'   => 'National Holidays 2025',
            'region' => 'National',
        ]);

        $holidays = [
            ['date' => '2025-01-01', 'name' => "New Year's Day",        'type' => 'national'],
            ['date' => '2025-01-26', 'name' => 'Republic Day',          'type' => 'national'],
            ['date' => '2025-03-14', 'name' => 'Holi',                  'type' => 'national'],
            ['date' => '2025-04-14', 'name' => 'Dr. Ambedkar Jayanti',  'type' => 'national'],
            ['date' => '2025-04-18', 'name' => 'Good Friday',           'type' => 'national'],
            ['date' => '2025-05-01', 'name' => 'Maharashtra Day',       'type' => 'regional'],
            ['date' => '2025-08-15', 'name' => 'Independence Day',      'type' => 'national'],
            ['date' => '2025-10-02', 'name' => 'Gandhi Jayanti',        'type' => 'national'],
            ['date' => '2025-10-02', 'name' => 'Dussehra',              'type' => 'national'],
            ['date' => '2025-10-23', 'name' => 'Diwali',                'type' => 'national'],
            ['date' => '2025-11-05', 'name' => 'Bhai Dooj',             'type' => 'national'],
            ['date' => '2025-11-15', 'name' => 'Guru Nanak Jayanti',    'type' => 'national'],
            ['date' => '2025-12-25', 'name' => 'Christmas Day',         'type' => 'national'],
        ];

        foreach ($holidays as $h) {
            Holiday::firstOrCreate(['holiday_calendar_id' => $calendar->id, 'date' => $h['date']], $h);
        }

        $this->command->info("✓ Sample data seeded: company, entity, departments, salary components, leave types, loan types.");
    }

    protected function leaveColor(string $code): string
    {
        return [
            'CL'  => '#3b82f6',
            'SL'  => '#f59e0b',
            'EL'  => '#10b981',
            'ML'  => '#ec4899',
            'PL'  => '#8b5cf6',
            'CO'  => '#14b8a6',
            'LOP' => '#ef4444',
            'BL'  => '#6b7280',
        ][$code] ?? '#6366f1';
    }
}
