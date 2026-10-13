<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily attendance exception mail (item 56) — one row per company holding
 * who should get the 10:30 AM "who hasn't arrived / is on leave" report.
 * Edited from the Attendance page by HR; the scheduled command
 * (attendance:daily-exception-mail) reads this per company and skips any
 * company with no recipients configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_mail_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->text('to_emails');
            $table->text('cc_emails')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_mail_recipients');
    }
};
