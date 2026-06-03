<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Syncs employee_status and date_of_leaving / date_of_joining
 * based on LFT (left) and JN (joined/rejoined) records in leave_tracker_records.
 *
 * Called after:
 *   1. Leave tracker file import (LeaveTrackerImporter::afterImport)
 *   2. Salary template download (WebSalaryController::template) — existing behaviour
 *
 * Pass $sheetEmpIds to limit the sync to only the employees touched by the
 * current upload.  Pass null (or empty array) to sync all employees.
 */
class LeaveTrackerStatusService
{
    /**
     * @param array|null $sheetEmpIds  employee_id strings from the uploaded file
     */
    public function sync(?array $sheetEmpIds = null): void
    {
        $today = now()->toDateString();

        $onlyThese = !empty($sheetEmpIds);

        // ── 1. Mark leavers inactive ──────────────────────────────────────────
        // Employee has an LFT record on or before today AND no JN record after it.
        $leaverSql = "
            SELECT lft.sheet_emp_id,
                   MAX(lft.leave_date) AS left_date
            FROM leave_tracker_records lft
            WHERE UPPER(lft.leave_type) = 'LFT'
              AND lft.leave_date <= ?
              AND NOT EXISTS (
                  SELECT 1 FROM leave_tracker_records jn
                  WHERE jn.sheet_emp_id = lft.sheet_emp_id
                    AND UPPER(jn.leave_type) = 'JN'
                    AND jn.leave_date > lft.leave_date
              )
        ";

        if ($onlyThese) {
            $placeholders = implode(',', array_fill(0, count($sheetEmpIds), '?'));
            $leaverSql   .= " AND lft.sheet_emp_id IN ({$placeholders})";
            $leaverSql   .= " GROUP BY lft.sheet_emp_id";
            $leavers      = DB::select($leaverSql, array_merge([$today], $sheetEmpIds));
        } else {
            $leaverSql .= " GROUP BY lft.sheet_emp_id";
            $leavers    = DB::select($leaverSql, [$today]);
        }

        foreach ($leavers as $row) {
            DB::table('employees')
                ->where(function ($q) use ($row) {
                    $q->where('employee_id',      $row->sheet_emp_id)
                      ->orWhere('external_emp_id', $row->sheet_emp_id);
                })
                ->where('employee_status', 'active')
                ->whereNull('date_of_leaving')
                ->whereNull('deleted_at')
                ->update([
                    'employee_status' => 'inactive',
                    'date_of_leaving' => $row->left_date,
                    'updated_at'      => now(),
                ]);
        }

        // ── 2. Re-activate rejoined employees ────────────────────────────────
        // Employee has a JN record AND their most recent LFT (if any) is BEFORE
        // that JN — i.e. they rejoined after leaving.
        $rejoinSql = "
            SELECT jn.sheet_emp_id,
                   MAX(jn.leave_date) AS join_date
            FROM leave_tracker_records jn
            WHERE UPPER(jn.leave_type) = 'JN'
              AND jn.leave_date <= ?
              AND NOT EXISTS (
                  SELECT 1 FROM leave_tracker_records lft2
                  WHERE lft2.sheet_emp_id = jn.sheet_emp_id
                    AND UPPER(lft2.leave_type) = 'LFT'
                    AND lft2.leave_date > jn.leave_date
              )
        ";

        if ($onlyThese) {
            $rePlaceholders = implode(',', array_fill(0, count($sheetEmpIds), '?'));
            $rejoinSql     .= " AND jn.sheet_emp_id IN ({$rePlaceholders})";
            $rejoinSql     .= " GROUP BY jn.sheet_emp_id";
            $rejoiners      = DB::select($rejoinSql, array_merge([$today], $sheetEmpIds));
        } else {
            $rejoinSql .= " GROUP BY jn.sheet_emp_id";
            $rejoiners  = DB::select($rejoinSql, [$today]);
        }

        foreach ($rejoiners as $row) {
            DB::table('employees')
                ->where(function ($q) use ($row) {
                    $q->where('employee_id',      $row->sheet_emp_id)
                      ->orWhere('external_emp_id', $row->sheet_emp_id);
                })
                ->whereIn('employee_status', ['inactive', 'on_notice'])
                ->whereNull('deleted_at')
                ->update([
                    'employee_status' => 'active',
                    'date_of_leaving' => null,
                    'updated_at'      => now(),
                ]);
        }
    }
}
