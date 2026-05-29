<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PfSetting;
use App\Models\EsicSetting;
use App\Models\PtSlab;
use App\Models\LwfSlab;
use App\Models\InvestmentDeclaration;
use App\Models\EmployeeTdsComputation;
use Carbon\Carbon;

class StatutoryService
{
    /**
     * Main entry: compute all statutory amounts for one employee in one period.
     * Returns array with all amounts.
     */
    public function compute(Employee $employee, array $earningsMap, PayrollPeriod $period): array
    {
        $grossSalary = array_sum($earningsMap);
        $basicSalary = $earningsMap['BASIC'] ?? 0;

        return [
            'pf_employee'   => $this->computePfEmployee($employee, $earningsMap, $period),
            'pf_employer'   => $this->computePfEmployer($employee, $earningsMap, $period),
            'esic_employee' => $this->computeEsicEmployee($employee, $grossSalary, $period),
            'esic_employer' => $this->computeEsicEmployer($employee, $grossSalary, $period),
            'pt_amount'     => $this->computePt($employee, $grossSalary, $period),
            'lwf_employee'  => $this->computeLwfEmployee($employee, $period),
            'lwf_employer'  => $this->computeLwfEmployer($employee, $period),
            'tds_amount'    => $this->computeMonthlyTds($employee, $grossSalary, $period),
        ];
    }

    // ──────────────────────────────────────────────
    // PROVIDENT FUND
    // ──────────────────────────────────────────────

    public function computePfEmployee(Employee $employee, array $earningsMap, PayrollPeriod $period): float
    {
        if (!$employee->is_pf_applicable) return 0;

        $settings = PfSetting::where('entity_id', $employee->entity_id)->first();
        if (!$settings) return 0;

        $pfWage = $this->getPfWage($employee, $earningsMap, $settings);
        return round($pfWage * ($settings->employee_contribution_pct / 100), 2);
    }

    public function computePfEmployer(Employee $employee, array $earningsMap, PayrollPeriod $period): float
    {
        if (!$employee->is_pf_applicable) return 0;

        $settings = PfSetting::where('entity_id', $employee->entity_id)->first();
        if (!$settings) return 0;

        $pfWage = $this->getPfWage($employee, $earningsMap, $settings);

        // Employer's PF = EPS + difference
        $eps      = round($pfWage * ($settings->eps_contribution_pct / 100), 2);
        $totalEr  = round($pfWage * ($settings->employer_contribution_pct / 100), 2);
        $epf      = $totalEr - $eps;  // EPF = Employer PF - EPS

        return round($totalEr, 2);
    }

    protected function getPfWage(Employee $employee, array $earningsMap, PfSetting $settings): float
    {
        // PF is calculated on components marked is_pf_applicable
        // For simplicity, usually it's Basic + DA
        $pfWage = ($earningsMap['BASIC'] ?? 0) + ($earningsMap['DA'] ?? 0);

        if ($settings->cap_at_wage_limit) {
            $pfWage = min($pfWage, $settings->wage_limit);
        }
        return $pfWage;
    }

    // ──────────────────────────────────────────────
    // ESIC
    // ──────────────────────────────────────────────

    public function computeEsicEmployee(Employee $employee, float $grossSalary, PayrollPeriod $period): float
    {
        if (!$employee->is_esic_applicable) return 0;

        $settings = EsicSetting::where('entity_id', $employee->entity_id)->first();
        if (!$settings) return 0;

        // ESIC applicable only if gross <= wage limit
        if ($grossSalary > $settings->wage_limit) return 0;

        return round($grossSalary * ($settings->employee_contribution_pct / 100), 2);
    }

    public function computeEsicEmployer(Employee $employee, float $grossSalary, PayrollPeriod $period): float
    {
        if (!$employee->is_esic_applicable) return 0;

        $settings = EsicSetting::where('entity_id', $employee->entity_id)->first();
        if (!$settings) return 0;

        if ($grossSalary > $settings->wage_limit) return 0;

        return round($grossSalary * ($settings->employer_contribution_pct / 100), 2);
    }

    // ──────────────────────────────────────────────
    // PROFESSIONAL TAX
    // ──────────────────────────────────────────────

    public function computePt(Employee $employee, float $grossSalary, PayrollPeriod $period): float
    {
        if (!$employee->is_pt_applicable) return 0;

        // Use entity's state for PT slabs
        $state = $employee->entity->state ?? $employee->branch?->state ?? null;
        if (!$state) return 0;

        $slab = PtSlab::where('entity_id', $employee->entity_id)
            ->where('state', $state)
            ->where('salary_from', '<=', $grossSalary)
            ->where(fn($q) => $q->whereNull('salary_to')->orWhere('salary_to', '>=', $grossSalary))
            ->where('is_active', true)
            ->orderByDesc('salary_from')
            ->first();

        return $slab ? $slab->pt_amount : 0;
    }

    // ──────────────────────────────────────────────
    // LABOUR WELFARE FUND
    // ──────────────────────────────────────────────

    public function computeLwfEmployee(Employee $employee, PayrollPeriod $period): float
    {
        if (!$employee->is_lwf_applicable) return 0;

        // LWF is often bi-annual (June & December) or annual
        $slab = LwfSlab::where('entity_id', $employee->entity_id)->where('is_active', true)->first();
        if (!$slab) return 0;

        // Check if this is a deduction month
        if (!$this->isLwfDeductionMonth($slab, $period->month)) return 0;

        return $slab->employee_contribution;
    }

    public function computeLwfEmployer(Employee $employee, PayrollPeriod $period): float
    {
        if (!$employee->is_lwf_applicable) return 0;

        $slab = LwfSlab::where('entity_id', $employee->entity_id)->where('is_active', true)->first();
        if (!$slab) return 0;

        if (!$this->isLwfDeductionMonth($slab, $period->month)) return 0;

        return $slab->employer_contribution;
    }

    protected function isLwfDeductionMonth(LwfSlab $slab, int $month): bool
    {
        return match($slab->deduction_month) {
            'june_december' => in_array($month, [6, 12]),
            'december'      => $month === 12,
            'march'         => $month === 3,
            default         => true,
        };
    }

    // ──────────────────────────────────────────────
    // TDS / INCOME TAX
    // ──────────────────────────────────────────────

    /**
     * Monthly TDS = (Annual Tax - Tax already deducted) / Remaining months in FY
     */
    public function computeMonthlyTds(Employee $employee, float $monthlyGross, PayrollPeriod $period): float
    {
        if (!$employee->is_tds_applicable) return 0;

        $fy             = $this->getFinancialYear($period->year, $period->month);
        $regime         = $employee->tds_regime;
        $remainingMonths = $this->getRemainingFyMonths($period->month);

        // YTD gross (sum of all payroll runs for this employee in this FY)
        $ytdGross = $this->getYtdGross($employee, $fy, $period->month);
        $projectedAnnualGross = $ytdGross + ($monthlyGross * $remainingMonths);

        // Investment declarations (approved amounts only)
        $declaration        = InvestmentDeclaration::where('employee_id', $employee->id)
            ->where('financial_year', $fy)
            ->where('regime', $regime)
            ->with('investments')
            ->first();

        $totalDeductions    = $this->computeIncomeTaxDeductions($declaration, $regime, $projectedAnnualGross);
        $taxableIncome      = max(0, $projectedAnnualGross - $totalDeductions);
        $annualTax          = $this->computeIncomeTax($taxableIncome, $regime, $fy);
        $educationCess      = round($annualTax * 0.04, 2); // 4% education cess
        $totalAnnualTax     = $annualTax + $educationCess;

        // Tax already deducted YTD
        $taxDeductedYtd = EmployeeTdsComputation::where('employee_id', $employee->id)
            ->where('financial_year', $fy)
            ->where('month', '<', $period->month)
            ->sum('monthly_tds');

        $monthlyTds = max(0, ($totalAnnualTax - $taxDeductedYtd) / $remainingMonths);

        // Save computation record
        EmployeeTdsComputation::updateOrCreate(
            ['employee_id' => $employee->id, 'financial_year' => $fy, 'month' => $period->month],
            [
                'gross_salary_ytd'     => $ytdGross,
                'taxable_income'       => $taxableIncome,
                'tax_payable_annual'   => $totalAnnualTax,
                'tax_paid_ytd'         => $taxDeductedYtd,
                'monthly_tds'          => round($monthlyTds, 2),
                'regime'               => $regime,
            ]
        );

        return round($monthlyTds, 2);
    }

    protected function computeIncomeTax(float $taxableIncome, string $regime, int $fy): float
    {
        $slabs = \App\Models\TdsSlab::where('financial_year', $fy)
            ->where('regime', $regime)
            ->orderBy('income_from')
            ->get();

        $tax = 0;
        foreach ($slabs as $slab) {
            $lower = $slab->income_from;
            $upper = $slab->income_to ?? PHP_FLOAT_MAX;
            if ($taxableIncome <= $lower) break;

            $taxable = min($taxableIncome, $upper) - $lower;
            $tax    += $taxable * ($slab->tax_rate / 100);
        }
        return round($tax, 2);
    }

    protected function computeIncomeTaxDeductions(?InvestmentDeclaration $declaration, string $regime, float $annualGross): float
    {
        $deductions = 50000; // Standard deduction

        if ($regime === 'old' && $declaration) {
            foreach ($declaration->investments ?? [] as $investment) {
                $deductions += $investment->approved_amount ?: $investment->declared_amount;
            }
            // HRA exemption
            $deductions += $this->computeHraExemption($declaration);
        }
        return $deductions;
    }

    protected function computeHraExemption(?InvestmentDeclaration $declaration): float
    {
        // HRA exemption = min(actual HRA, 50% basic for metro / 40% for non-metro, rent paid - 10% basic)
        // Simplified — actual logic reads from declaration_hra
        if (!$declaration) return 0;
        $hra = $declaration->hra()->first();
        if (!$hra) return 0;
        // Detailed calculation omitted for brevity — implement based on city type
        return 0;
    }

    protected function getFinancialYear(int $year, int $month): int
    {
        return $month >= 4 ? $year : $year - 1;
    }

    protected function getRemainingFyMonths(int $currentMonth): int
    {
        // FY ends in March (month 3)
        $fyEndMonth = 3;
        $remaining  = $currentMonth <= 3
            ? ($fyEndMonth - $currentMonth + 1)
            : (12 - $currentMonth + $fyEndMonth + 1);
        return max(1, $remaining);
    }

    protected function getYtdGross(Employee $employee, int $fy, int $currentMonth): float
    {
        // Sum net earnings from payroll records in this FY up to last month
        return \App\Models\PayrollEmployeeRecord::where('employee_id', $employee->id)
            ->whereHas('payrollRun.period', function ($q) use ($fy, $currentMonth) {
                $q->where(function ($q) use ($fy, $currentMonth) {
                    // April of FY to currentMonth-1
                    if ($currentMonth <= 3) {
                        // Current month is Jan/Feb/Mar — include Apr-Dec of last year and Jan to current-1
                        $q->where(function ($q) use ($fy) {
                            $q->where('year', $fy)->where('month', '>=', 4);
                        })->orWhere(function ($q) use ($fy, $currentMonth) {
                            $q->where('year', $fy + 1)->where('month', '<', $currentMonth);
                        });
                    } else {
                        $q->where('year', $fy)->where('month', '<', $currentMonth)->where('month', '>=', 4);
                    }
                });
            })
            ->sum('total_earnings');
    }
}
