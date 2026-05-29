<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_bio_ids
 * ─────────────────
 * One employee can have multiple biometric IDs — one per device or office location.
 * Attendance records are fetched/stored by bio_id, not by employee_id directly.
 *
 * Lookup path for attendance:
 *   raw_bio_id  →  employee_bio_ids.bio_id  →  employee_id  →  employees
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bio_ids', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();

            // The actual ID programmed into the biometric machine
            $table->string('bio_id', 50)->unique();

            // Optional: which device / machine this ID belongs to
            $table->string('device_name', 100)->nullable();

            // Optional: branch / office where this device is installed
            $table->string('device_location', 100)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Fast lookup: find employee from a raw bio_id scan
            $table->index('bio_id');

            // List all bio IDs for a given employee
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bio_ids');
    }
};
