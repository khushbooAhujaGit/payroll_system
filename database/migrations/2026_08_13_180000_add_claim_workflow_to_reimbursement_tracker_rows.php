<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing HR-manual-entry/import tracker so employees can
 * self-submit a claim (with a supporting document) that routes to a single
 * designated approver, and so Admin can record payment status afterward.
 * `emp_id`/`employee_name` stay untouched — the existing manual-add/import
 * flows keep working exactly as before; `employee_id` is only populated for
 * self-submitted claims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('emp_id')->constrained('employees')->nullOnDelete();
            $table->string('document_path')->nullable()->after('image_filename');
            $table->string('document_name')->nullable()->after('document_path');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_remarks')->nullable()->after('approved_at');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->after('approval_remarks');
            $table->date('payment_date')->nullable()->after('payment_status');
            $table->string('payment_reference')->nullable()->after('payment_date');
        });

        // The existing manual-add/import flows auto-approve (default stays
        // 'approved', untouched) — only self-submitted claims explicitly set
        // 'pending', so this is additive and doesn't change existing rows.
        DB::statement("ALTER TABLE reimbursement_tracker_rows MODIFY status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reimbursement_tracker_rows MODIFY status ENUM('approved', 'rejected') DEFAULT 'approved'");

        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'document_path', 'document_name',
                'approved_at', 'approval_remarks',
                'payment_status', 'payment_date', 'payment_reference',
            ]);
        });
    }
};
