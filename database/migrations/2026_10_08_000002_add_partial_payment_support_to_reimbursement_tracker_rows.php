<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supports "approved but only partially paid" claims: payment_status gains
 * a 'partial' value, paid_amount (TEXT — encrypted, same as `amount`; see
 * EncryptedDecimal) records how much of the approved amount was actually
 * paid, and the carried_forward_* self-reference links an original row to
 * the new row auto-created for the remaining balance in the following
 * month's tracker (see WebMyReimbursementController::updatePayment()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->text('paid_amount')->nullable()->after('payment_reference');
            $table->foreignId('carried_forward_from_row_id')->nullable()->after('paid_amount')
                ->constrained('reimbursement_tracker_rows')->nullOnDelete();
            $table->foreignId('carried_forward_to_row_id')->nullable()->after('carried_forward_from_row_id')
                ->constrained('reimbursement_tracker_rows')->nullOnDelete();
        });

        DB::statement("ALTER TABLE reimbursement_tracker_rows MODIFY payment_status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reimbursement_tracker_rows MODIFY payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid'");

        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carried_forward_to_row_id');
            $table->dropConstrainedForeignId('carried_forward_from_row_id');
            $table->dropColumn('paid_amount');
        });
    }
};
