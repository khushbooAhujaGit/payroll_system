<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY document_type ENUM(
            'pan', 'aadhaar', 'passport', 'driving_license', 'voter_id',
            'offer_letter', 'appointment_letter', 'increment_letter',
            'nda', 'agreement', 'educational_certificate', 'marksheet',
            'experience_letter', 'reference_letter', 'bank_proof',
            'cancelled_cheque', 'uan_document', 'address_proof', 'photo', 'other'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY document_type ENUM(
            'pan', 'aadhaar', 'passport', 'driving_license', 'voter_id',
            'offer_letter', 'appointment_letter', 'increment_letter',
            'nda', 'agreement', 'educational_certificate', 'experience_letter',
            'bank_proof', 'address_proof', 'photo', 'other'
        )");
    }
};
