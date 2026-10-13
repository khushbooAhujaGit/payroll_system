<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily attendance exception mail (item 56) — a single global To/Cc
 * recipient list (one combined email covers every company, each as its own
 * section — see SendDailyAttendanceExceptionMail), edited by HR from the
 * Attendance page. Singleton table: at most one row. to_emails/cc_emails
 * are newline/comma-separated addresses (see parseEmailList()) so the
 * settings form can just use a plain textarea.
 */
class AttendanceMailRecipient extends Model
{
    protected $table    = 'attendance_mail_recipients';
    protected $fillable = ['to_emails', 'cc_emails', 'updated_by'];

    /** Parsed, de-duplicated To addresses as an array. */
    public function toList(): array
    {
        return self::parseEmailList($this->to_emails);
    }

    /** Parsed, de-duplicated Cc addresses as an array. */
    public function ccList(): array
    {
        return self::parseEmailList($this->cc_emails);
    }

    public static function parseEmailList(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        return collect(preg_split('/[\r\n,;]+/', $raw))
            ->map(fn($e) => trim($e))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
