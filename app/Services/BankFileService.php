<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use App\Models\BankTransferFile;
use Illuminate\Support\Facades\Storage;

/**
 * Generates bank salary credit files in formats accepted by major Indian banks.
 * Supported: HDFC, ICICI, SBI, Axis, Kotak — NEFT/RTGS/NACH format.
 */
class BankFileService
{
    public function generate(PayrollRun $payrollRun, string $bankName, string $fileFormat): BankTransferFile
    {
        $records = PayrollEmployeeRecord::where('payroll_run_id', $payrollRun->id)
            ->where('status', '!=', 'blocked')
            ->where('is_blocked', false)
            ->with(['employee.primaryBank'])
            ->get();

        $totalAmount = $records->sum(fn($r) => $r->manual_net_override ?? $r->net_salary);
        $content     = match (strtoupper($bankName)) {
            'HDFC'  => $this->generateHdfc($records, $payrollRun, $fileFormat),
            'ICICI' => $this->generateIcici($records, $payrollRun, $fileFormat),
            'SBI'   => $this->generateSbi($records, $payrollRun, $fileFormat),
            'AXIS'  => $this->generateAxis($records, $payrollRun, $fileFormat),
            'KOTAK' => $this->generateKotak($records, $payrollRun, $fileFormat),
            default => $this->generateGenericNeft($records, $payrollRun),
        };

        $fileName = sprintf(
            'bank_files/%s/%s_%s_%s_%s_%s.txt',
            $payrollRun->entity_id,
            strtolower($bankName),
            strtolower($fileFormat),
            $payrollRun->period->year,
            str_pad($payrollRun->period->month, 2, '0', STR_PAD_LEFT),
            now()->format('His')
        );

        // Storage::put($fileName, $content);

        $folder = public_path('uploads/bank-files');

        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }

        // $fileName at this point is just the filename string like "HDFC_2024_05.txt"
        file_put_contents($folder . '/' . basename($fileName), $content);

        $fileName = 'uploads/bank-files/' . basename($fileName);

        $bankFile = BankTransferFile::create([
            'payroll_run_id'  => $payrollRun->id,
            'entity_id'       => $payrollRun->entity_id,
            'bank_name'       => $bankName,
            'file_format'     => $fileFormat,
            'total_records'   => $records->count(),
            'total_amount'    => $totalAmount,
            'file_path'       => $fileName,
            'reference_number' => $this->generateReferenceNumber($payrollRun, $bankName),
            'status'          => 'generated',
        ]);

        return $bankFile;
    }

    // ─────────────────────────────────────────────
    // HDFC FORMAT
    // Payment Type = "NEFT" or "RTGS"
    // Format: Debit Account | Credit Account | IFSC | Amount | Beneficiary Name | Payment Mode | Remarks
    // ─────────────────────────────────────────────

    protected function generateHdfc($records, PayrollRun $run, string $mode): string
    {
        $entity       = $run->entity;
        $debitAccount = \App\Models\SystemSetting::getValue('hdfc_debit_account', $entity->id) ?? '';

        $lines = [];
        // HDFC Header
        $lines[] = implode(',', [
            'Payment Date',
            'Debit Account No',
            'Beneficiary Name',
            'Beneficiary Account No',
            'Beneficiary IFSC',
            'Amount',
            'Payment Mode',
            'Remarks',
            'Email ID',
            'Mobile',
        ]);

        foreach ($records as $record) {
            $employee = $record->employee;
            $bank     = $employee->primaryBank;
            if (!$bank) continue;

            $amount = number_format($record->manual_net_override ?? $record->net_salary, 2, '.', '');
            if ((float)$amount <= 0) continue;

            $lines[] = implode(',', [
                now()->format('d/m/Y'),
                $debitAccount,
                '"' . $this->sanitize($bank->account_holder_name) . '"',
                $bank->account_number,
                strtoupper($bank->ifsc_code),
                $amount,
                strtoupper($mode),
                '"Salary ' . $run->period->year . '-' . str_pad($run->period->month, 2, '0', STR_PAD_LEFT) . '"',
                $employee->official_email ?? '',
                $employee->mobile ?? '',
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ─────────────────────────────────────────────
    // ICICI FORMAT
    // ─────────────────────────────────────────────

    protected function generateIcici($records, PayrollRun $run, string $mode): string
    {
        $entity       = $run->entity;
        $debitAccount = \App\Models\SystemSetting::getValue('icici_debit_account', $entity->id) ?? '';

        $lines = [];
        $lines[] = "NEFT RTGS FILE FOR ICICI BANK";
        $lines[] = implode('|', [
            'SRNO',
            'BENE_ACNO',
            'BENE_NAME',
            'BENE_BANK_IFSC',
            'AMT',
            'PAYMENT_TYPE',
            'EMAIL',
            'MOBILE',
            'REMARKS',
        ]);

        $srNo = 1;
        foreach ($records as $record) {
            $employee = $record->employee;
            $bank     = $employee->primaryBank;
            if (!$bank) continue;

            $amount = number_format($record->manual_net_override ?? $record->net_salary, 2, '.', '');
            if ((float)$amount <= 0) continue;

            $lines[] = implode('|', [
                $srNo++,
                $bank->account_number,
                $this->sanitize($bank->account_holder_name),
                strtoupper($bank->ifsc_code),
                $amount,
                $mode,
                $employee->official_email ?? '',
                $employee->mobile ?? '',
                'SALARY_' . $run->period->year . str_pad($run->period->month, 2, '0', STR_PAD_LEFT),
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ─────────────────────────────────────────────
    // SBI FORMAT
    // ─────────────────────────────────────────────

    protected function generateSbi($records, PayrollRun $run, string $mode): string
    {
        $lines = [];
        // SBI CINB format
        $lines[] = implode(',', [
            'SNo',
            'BeneName',
            'BeneAccNo',
            'BeneIFSC',
            'Amount',
            'PaymentMode',
            'MobileNo',
            'EmailId',
            'Narration',
        ]);

        $srNo = 1;
        foreach ($records as $record) {
            $employee = $record->employee;
            $bank     = $employee->primaryBank;
            if (!$bank) continue;

            $amount = number_format($record->manual_net_override ?? $record->net_salary, 2, '.', '');
            if ((float)$amount <= 0) continue;

            $lines[] = implode(',', [
                $srNo++,
                '"' . $this->sanitize($bank->account_holder_name) . '"',
                $bank->account_number,
                strtoupper($bank->ifsc_code),
                $amount,
                $mode,
                $employee->mobile ?? '',
                $employee->official_email ?? '',
                '"Salary ' . $run->period->year . '/' . str_pad($run->period->month, 2, '0', STR_PAD_LEFT) . '"',
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ─────────────────────────────────────────────
    // AXIS FORMAT
    // ─────────────────────────────────────────────

    protected function generateAxis($records, PayrollRun $run, string $mode): string
    {
        $lines = [];
        $lines[] = implode(',', [
            'Sl. No.',
            'Account Number',
            'IFSC Code',
            'Account Name',
            'Amount',
            'Payment Mode',
            'Mobile',
            'Email',
            'Narration',
        ]);

        $srNo = 1;
        foreach ($records as $record) {
            $employee = $record->employee;
            $bank     = $employee->primaryBank;
            if (!$bank) continue;

            $amount = number_format($record->manual_net_override ?? $record->net_salary, 2, '.', '');
            if ((float)$amount <= 0) continue;

            $lines[] = implode(',', [
                $srNo++,
                $bank->account_number,
                strtoupper($bank->ifsc_code),
                '"' . $this->sanitize($bank->account_holder_name) . '"',
                $amount,
                $mode,
                $employee->mobile ?? '',
                $employee->official_email ?? '',
                '"SALARY ' . $run->period->year . '-' . str_pad($run->period->month, 2, '0', STR_PAD_LEFT) . '"',
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ─────────────────────────────────────────────
    // KOTAK FORMAT
    // ─────────────────────────────────────────────

    protected function generateKotak($records, PayrollRun $run, string $mode): string
    {
        $lines = [];
        $lines[] = implode(',', [
            'SNo',
            'BeneName',
            'BeneAccountNo',
            'BeneIFSC',
            'Amount',
            'PaymentType',
            'Narration',
        ]);

        $srNo = 1;
        foreach ($records as $record) {
            $employee = $record->employee;
            $bank     = $employee->primaryBank;
            if (!$bank) continue;

            $amount = number_format($record->manual_net_override ?? $record->net_salary, 2, '.', '');
            if ((float)$amount <= 0) continue;

            $lines[] = implode(',', [
                $srNo++,
                '"' . $this->sanitize($bank->account_holder_name) . '"',
                $bank->account_number,
                strtoupper($bank->ifsc_code),
                $amount,
                $mode,
                'SALARY',
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ─────────────────────────────────────────────
    // GENERIC NEFT CSV (fallback)
    // ─────────────────────────────────────────────

    protected function generateGenericNeft($records, PayrollRun $run): string
    {
        $lines = [];
        $lines[] = implode(',', [
            'SNo',
            'EmployeeID',
            'EmployeeName',
            'BankName',
            'AccountNo',
            'IFSC',
            'Amount',
            'Narration',
        ]);

        $srNo = 1;
        foreach ($records as $record) {
            $employee = $record->employee;
            $bank     = $employee->primaryBank;
            if (!$bank) continue;

            $amount = number_format($record->manual_net_override ?? $record->net_salary, 2, '.', '');
            if ((float)$amount <= 0) continue;

            $lines[] = implode(',', [
                $srNo++,
                $employee->employee_id,
                '"' . $employee->full_name . '"',
                '"' . ($bank->bank_name ?? '') . '"',
                $bank->account_number,
                strtoupper($bank->ifsc_code),
                $amount,
                'SALARY',
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function sanitize(string $name): string
    {
        // Remove special chars that break CSV / fixed-width formats
        return preg_replace('/[,|"\'\\\\]/', ' ', trim($name));
    }

    protected function generateReferenceNumber(PayrollRun $run, string $bankName): string
    {
        return strtoupper(substr($bankName, 0, 3))
            . $run->period->year
            . str_pad($run->period->month, 2, '0', STR_PAD_LEFT)
            . str_pad($run->id, 5, '0', STR_PAD_LEFT);
    }
}
