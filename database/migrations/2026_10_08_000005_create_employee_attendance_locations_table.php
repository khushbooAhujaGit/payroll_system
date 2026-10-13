<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One mapped point per employee (re-mapping replaces it, no history/picker) —
 * WFH/Client Location employees set this on first app login; WFO employees
 * don't need one, since any company office location is valid for them.
 * See ATTENDANCE_APP_PLAN.md §3.1.
 *
 * InnoDB (not this DB's common MyISAM legacy) — a fresh table has no reason
 * to inherit MyISAM's lack of real transactions; see CLAUDE.md's MyISAM note.
 * No real FK constraint on employee_id — `employees` is MyISAM, which
 * InnoDB refuses to reference (error 1824), so this is a plain indexed
 * column with application-level integrity only, same as every other
 * cross-table reference already in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance_locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('employee_id')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('label', 100)->nullable();
            $table->timestamp('mapped_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_locations');
    }
};
