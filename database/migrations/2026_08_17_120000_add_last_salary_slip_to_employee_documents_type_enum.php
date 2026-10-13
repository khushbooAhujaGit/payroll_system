<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY document_type ENUM(
            'pan', 'aadhaar', 'passport', 'driving_license', 'voter_id',
            'offer_letter', 'appointment_letter', 'increment_letter',
            'nda', 'agreement', 'educational_certificate', 'marksheet',
            'experience_letter', 'reference_letter', 'bank_proof',
            'cancelled_cheque', 'uan_document', 'last_salary_slip',
            'address_proof', 'photo', 'other'
        )");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY document_type ENUM(
            'pan', 'aadhaar', 'passport', 'driving_license', 'voter_id',
            'offer_letter', 'appointment_letter', 'increment_letter',
            'nda', 'agreement', 'educational_certificate', 'marksheet',
            'experience_letter', 'reference_letter', 'bank_proof',
            'cancelled_cheque', 'uan_document', 'address_proof', 'photo', 'other'
        )");
    }
};
