<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // COMPANIES  (top-level legal entity group)
        // ─────────────────────────────────────────────
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('pincode', 10)->nullable();
            $table->string('pan')->nullable();
            $table->string('tan')->nullable();
            $table->string('gstin')->nullable();
            $table->string('cin')->nullable();           // company incorporation number
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─────────────────────────────────────────────
        // ENTITIES  (legal entities / payroll entities under a company)
        // ─────────────────────────────────────────────
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('pan')->nullable();
            $table->string('tan')->nullable();
            $table->string('gstin')->nullable();
            $table->string('pf_number')->nullable();      // PF registration number
            $table->string('esic_number')->nullable();
            $table->string('pt_number')->nullable();      // Professional Tax
            $table->string('lwf_number')->nullable();     // Labour Welfare Fund
            $table->string('payroll_cycle')->default('monthly'); // monthly / weekly
            $table->integer('payroll_cutoff_day')->default(31); // day of month payroll cuts off
            $table->string('state', 100)->nullable();     // for PT slab lookup
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─────────────────────────────────────────────
        // BRANCHES
        // ─────────────────────────────────────────────
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // DEPARTMENTS
        // ─────────────────────────────────────────────
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // DESIGNATIONS
        // ─────────────────────────────────────────────
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // GRADES / BANDS
        // ─────────────────────────────────────────────
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->integer('level')->default(1);         // numeric level for sorting
            $table->decimal('min_ctc', 15, 2)->nullable();
            $table->decimal('max_ctc', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // COST CENTERS
        // ─────────────────────────────────────────────
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->enum('type', ['cost_center', 'profit_center'])->default('cost_center');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // HOLIDAY CALENDARS
        // ─────────────────────────────────────────────
        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->integer('year');
            $table->string('region')->nullable();  // state / branch
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('holiday_calendar_id')->nullable();
            $table->date('date');
            $table->string('name');
            $table->enum('type', ['national', 'regional', 'optional', 'company'])->default('national');
            $table->text('description')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('companies');
    }
};
