<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\SalaryShadowLog;
use Illuminate\Http\Request;

/**
 * Owner-only salary shadow log viewer.
 * Accessible only when the request carries the correct OWNER_SECRET token
 * defined in .env — never linked from the main UI.
 */
class ShadowLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorise($request);

        $query = SalaryShadowLog::with('employee')
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($request->filled('month')) {
            $query->where('import_month', $request->month);
        }

        if ($request->filled('emp')) {
            $query->where('emp_id_text', 'like', '%' . $request->emp . '%');
        }

        $logs   = $query->paginate(100)->withQueryString();
        $months = SalaryShadowLog::selectRaw('import_month')
            ->whereNotNull('import_month')
            ->distinct()
            ->orderByDesc('import_month')
            ->pluck('import_month');

        $token = $request->query('t') ?? $request->header('X-Owner-Token');

        return view('owner.shadow-log', compact('logs', 'months', 'token'));
    }

    public function export(Request $request)
    {
        $this->authorise($request);

        $query = SalaryShadowLog::with('employee')->orderByDesc('effective_from')->orderByDesc('id');

        if ($request->filled('month')) {
            $query->where('import_month', $request->month);
        }

        $rows = $query->get();

        $headers = ['#', 'Emp ID', 'Employee Name', 'Gross Monthly', 'CTC Monthly', 'CTC Annual', 'Effective From', 'Month', 'Source', 'Recorded At'];
        $data    = $rows->map(fn($r, $i) => [
            $i + 1,
            $r->emp_id_text,
            $r->employee_name,
            $r->gross_monthly,
            $r->ctc_monthly,
            $r->ctc_annual,
            $r->effective_from?->format('d-m-Y'),
            $r->import_month,
            $r->source,
            $r->created_at?->format('d-m-Y H:i'),
        ]);

        return $this->streamXlsx('salary_shadow_' . now()->format('Ymd_His'), $headers, $data);
    }

    // ── Guard ─────────────────────────────────────────────────────────────────

    private function authorise(Request $request): void
    {
        $secret = config('app.owner_secret');

        if (empty($secret)) {
            abort(404); // OWNER_SECRET not set → route doesn't exist
        }

        $provided = $request->query('t') ?? $request->header('X-Owner-Token');

        if (!hash_equals($secret, (string) $provided)) {
            abort(404); // Wrong token → look like a 404 not a 403
        }
    }
}
