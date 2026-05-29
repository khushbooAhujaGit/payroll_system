<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add ctc encrypted columns to employee_salaries ────────────────
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->text('ctc_annual_enc')->nullable()->after('gross_monthly_enc');
            $table->text('ctc_monthly_enc')->nullable()->after('ctc_annual_enc');
        });

        // ── 2. Encrypt existing plaintext rows, then zero out plaintext ───────
        $rows = DB::table('employee_salaries')->get();
        foreach ($rows as $row) {
            $updates = [];

            // gross_monthly_enc: re-encrypt if missing, keep if present
            if (empty($row->gross_monthly_enc) && $row->gross_monthly > 0) {
                $updates['gross_monthly_enc'] = encrypt((string) $row->gross_monthly);
            }
            // ctc_annual_enc: encrypt existing plaintext
            if ($row->ctc_annual > 0) {
                $updates['ctc_annual_enc']  = encrypt((string) $row->ctc_annual);
                $updates['ctc_monthly_enc'] = encrypt((string) $row->ctc_monthly);
            }
            // Zero out plaintext — real values live only in *_enc columns
            $updates['gross_monthly'] = 0;
            $updates['ctc_annual']    = 0;
            $updates['ctc_monthly']   = 0;

            if (!empty($updates)) {
                DB::table('employee_salaries')->where('id', $row->id)->update($updates);
            }
        }

        // ── 3. Create salary_raw_uploads audit table ──────────────────────────
        Schema::create('salary_raw_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('emp_id_text', 50)->comment('Raw Emp ID from import file');

            // All monetary values stored encrypted — no plaintext in this table either
            $table->text('gross_monthly_enc');
            $table->text('ctc_annual_enc');
            $table->text('ctc_monthly_enc');

            $table->date('effective_from');
            $table->string('import_month', 7)->nullable()->comment('YYYY-MM from upload picker');
            $table->enum('source', ['import', 'manual'])->default('import');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_raw_uploads');
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn(['ctc_annual_enc', 'ctc_monthly_enc']);
        });
    }
};
