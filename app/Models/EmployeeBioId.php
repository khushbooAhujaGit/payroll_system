<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biometric / attendance machine IDs for an employee.
 * One employee → many bio IDs (one per device/location).
 *
 * Attendance lookup path:
 *   raw scan  →  bio_id  →  employee_id  →  Employee
 */
class EmployeeBioId extends Model
{

    protected $table = "employee_bio_ids";

    // Machine type → device label mapping
    // 'IV' => 'OTHER' is a virtual "machine" for employees with no physical
    // punch device (Work From Home / Client Location) — see WfhAttendanceImporter.
    const MACHINE_MAP = [
        'I'   => '10',
        'II'  => '12',
        'III' => 'K',
        'IV'  => 'OTHER',
    ];

    protected $fillable = [
        'employee_id',
        'bio_id_full',
        'bio_type',
        'bio_number',
        'machine_name',
        'is_active',
    ];

    protected $casts = [
        'bio_number' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Parse a Bio ID string like "I-20" into its parts.
     * Returns ['bio_type'=>'I', 'bio_number'=>20, 'machine_name'=>'10', 'bio_id_full'=>'I-20']
     * or null if the format is unrecognised.
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if (!preg_match('/^(IV|III|II|I)-(\d+)$/i', $raw, $m)) {
            return null;
        }
        $type = strtoupper($m[1]);
        return [
            'bio_id_full'  => strtoupper($raw),
            'bio_type'     => $type,
            'bio_number'   => (int) $m[2],
            'machine_name' => self::MACHINE_MAP[$type] ?? $type,
        ];
    }

    /**
     * Find the employee who owns a given Bio ID full string.
     * Used by the attendance ingestion pipeline.
     */
    public static function findEmployee(string $bioIdFull): ?Employee
    {
        $id = static::where('bio_id_full', strtoupper(trim($bioIdFull)))
            ->where('is_active', true)
            ->value('employee_id');

        return $id ? Employee::find($id) : null;
    }
}
