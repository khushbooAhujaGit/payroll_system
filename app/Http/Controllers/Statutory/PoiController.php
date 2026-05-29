<?php

namespace App\Http\Controllers\Statutory;

use App\Http\Controllers\Controller;
use App\Models\PoiSubmission;
use App\Models\InvestmentDeclaration;
use App\Models\DeclarationInvestment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoiController extends Controller
{
    // ─────────────────────────────────────────────
    // EMPLOYEE: UPLOAD PROOF
    // ─────────────────────────────────────────────

    public function uploadProof(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'investment_declaration_id'  => 'required|exists:investment_declarations,id',
            'declaration_investment_id'  => 'nullable|exists:declaration_investments,id',
            'section'                    => 'required|string',
            'investment_type'            => 'required|string',
            'claimed_amount'             => 'required|numeric|min:1',
            'file'                       => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks'                    => 'nullable|string',
        ]);

        $employee    = auth()->user()->employee;
        $declaration = InvestmentDeclaration::where('id', $validated['investment_declaration_id'])
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $filePath = $request->file('file')->store(
            "poi/{$employee->id}/{$declaration->financial_year}/{$validated['section']}",
            'local'
        );

        // Check if proof already submitted for same declaration_investment
        $existing = PoiSubmission::where('investment_declaration_id', $declaration->id)
            ->where('declaration_investment_id', $validated['declaration_investment_id'])
            ->where('section', $validated['section'])
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing && $existing->status === 'approved') {
            return response()->json(['message' => 'Proof already approved for this investment.'], 422);
        }

        // If rejected, allow resubmit
        $poi = PoiSubmission::updateOrCreate(
            [
                'investment_declaration_id' => $declaration->id,
                'declaration_investment_id' => $validated['declaration_investment_id'],
                'section'                   => $validated['section'],
                'status'                    => 'rejected',  // only update rejected ones
            ],
            [
                'financial_year'   => $declaration->financial_year,
                'investment_type'  => $validated['investment_type'],
                'claimed_amount'   => $validated['claimed_amount'],
                'file_path'        => $filePath,
                'file_name'        => $request->file('file')->getClientOriginalName(),
                'remarks'          => $validated['remarks'] ?? null,
                'status'           => 'pending',
                'approved_amount'  => null,
                'hr_remarks'       => null,
                'reviewed_by'      => null,
                'reviewed_at'      => null,
            ]
        );

        if (!$poi->wasRecentlyCreated && !$poi->wasChanged()) {
            // Create new if no rejected found
            $poi = PoiSubmission::create([
                'investment_declaration_id' => $declaration->id,
                'declaration_investment_id' => $validated['declaration_investment_id'] ?? null,
                'financial_year'            => $declaration->financial_year,
                'section'                   => $validated['section'],
                'investment_type'           => $validated['investment_type'],
                'claimed_amount'            => $validated['claimed_amount'],
                'file_path'                 => $filePath,
                'file_name'                 => $request->file('file')->getClientOriginalName(),
                'remarks'                   => $validated['remarks'] ?? null,
                'status'                    => 'pending',
            ]);
        }

        return response()->json(['message' => 'Proof uploaded successfully.', 'data' => $poi], 201);
    }

    // ─────────────────────────────────────────────
    // EMPLOYEE: MY SUBMISSIONS
    // ─────────────────────────────────────────────

    public function mySubmissions(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $fy       = $request->get('fy', $this->currentFy());

        $submissions = PoiSubmission::where('financial_year', $fy)
            ->whereHas('investmentDeclaration', fn($q) => $q->where('employee_id', $employee->id))
            ->orderBy('section')
            ->get();

        return response()->json(['data' => $submissions]);
    }

    // ─────────────────────────────────────────────
    // HR: LIST ALL PENDING POI
    // ─────────────────────────────────────────────

    public function pendingReview(Request $request): JsonResponse
    {
        $this->authorize('poi.review');

        $submissions = PoiSubmission::with([
            'investmentDeclaration.employee:id,employee_id,first_name,last_name',
            'declarationInvestment',
        ])
        ->whereHas('investmentDeclaration.employee', fn($q) => $q->where('entity_id', $request->entity_id))
        ->where('financial_year', $request->get('fy', $this->currentFy()))
        ->when($request->status, fn($q) => $q->where('status', $request->status), fn($q) => $q->where('status', 'pending'))
        ->orderByDesc('created_at')
        ->paginate(50);

        return response()->json($submissions);
    }

    // ─────────────────────────────────────────────
    // HR: REVIEW (Approve / Reject)
    // ─────────────────────────────────────────────

    public function review(Request $request, PoiSubmission $poi): JsonResponse
    {
        $this->authorize('poi.review');

        $validated = $request->validate([
            'action'          => 'required|in:approved,rejected,resubmit',
            'approved_amount' => 'required_if:action,approved|nullable|numeric|min:0',
            'hr_remarks'      => 'nullable|string|max:500',
        ]);

        $poi->update([
            'status'          => $validated['action'],
            'approved_amount' => $validated['action'] === 'approved' ? $validated['approved_amount'] : null,
            'hr_remarks'      => $validated['hr_remarks'] ?? null,
            'reviewed_by'     => auth()->id(),
            'reviewed_at'     => now(),
        ]);

        // Sync approved amount to declaration investment
        if ($poi->declaration_investment_id && $validated['action'] === 'approved') {
            DeclarationInvestment::where('id', $poi->declaration_investment_id)->update([
                'approved_amount' => $validated['approved_amount'],
                'proof_status'    => 'approved',
            ]);
        } elseif ($poi->declaration_investment_id && $validated['action'] === 'rejected') {
            DeclarationInvestment::where('id', $poi->declaration_investment_id)->update([
                'proof_status' => 'rejected',
            ]);
        }

        return response()->json([
            'message' => "Proof of investment {$validated['action']}.",
            'data'    => $poi->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────
    // HR: BULK REVIEW
    // ─────────────────────────────────────────────

    public function bulkReview(Request $request): JsonResponse
    {
        $this->authorize('poi.review');

        $validated = $request->validate([
            'submissions'              => 'required|array|min:1',
            'submissions.*.id'         => 'required|exists:poi_submissions,id',
            'submissions.*.action'     => 'required|in:approved,rejected',
            'submissions.*.amount'     => 'nullable|numeric|min:0',
        ]);

        $approved = 0;
        $rejected = 0;

        foreach ($validated['submissions'] as $item) {
            $poi = PoiSubmission::find($item['id']);
            $poi->update([
                'status'          => $item['action'],
                'approved_amount' => $item['action'] === 'approved' ? ($item['amount'] ?? $poi->claimed_amount) : null,
                'reviewed_by'     => auth()->id(),
                'reviewed_at'     => now(),
            ]);
            $item['action'] === 'approved' ? $approved++ : $rejected++;
        }

        return response()->json(['message' => "{$approved} approved, {$rejected} rejected."]);
    }

    // ─────────────────────────────────────────────
    // DOWNLOAD PROOF FILE
    // ─────────────────────────────────────────────

    public function downloadProof(PoiSubmission $poi): \Symfony\Component\HttpFoundation\Response
    {
        if (!\Illuminate\Support\Facades\Storage::exists($poi->file_path)) {
            abort(404, 'File not found.');
        }

        return response()->download(
            storage_path("app/{$poi->file_path}"),
            $poi->file_name
        );
    }

    // ─────────────────────────────────────────────
    // SUMMARY (per employee, per FY)
    // ─────────────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('poi.review');

        $fy      = $request->get('fy', $this->currentFy());
        $summary = PoiSubmission::selectRaw('
            status,
            section,
            COUNT(*) as count,
            SUM(claimed_amount) as total_claimed,
            SUM(approved_amount) as total_approved
        ')
        ->where('financial_year', $fy)
        ->whereHas('investmentDeclaration.employee', fn($q) => $q->where('entity_id', $request->entity_id))
        ->groupBy('status', 'section')
        ->get();

        return response()->json(['data' => $summary]);
    }

    private function currentFy(): int
    {
        return now()->month >= 4 ? now()->year : now()->year - 1;
    }
}
