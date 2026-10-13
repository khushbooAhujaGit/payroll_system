<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskComment;
use App\Models\User;
use App\Services\PublicFileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpdeskController extends Controller
{
    public function __construct(protected PublicFileStore $publicFileStore) {}

    // ─────────────────────────────────────────────
    // CATEGORIES
    // ─────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $categories = HelpdeskCategory::where('company_id', $request->company_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn($q) => $q->where('is_active', true)])
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('helpdesk.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'          => 'required|string|max:100',
            'parent_id'     => 'nullable|exists:helpdesk_categories,id',
            'assigned_team' => 'required|in:hr,payroll,it,admin,finance',
            'sla_hours'     => 'required|integer|min:1',
        ]);

        $category = HelpdeskCategory::create($validated);
        return response()->json(['data' => $category], 201);
    }

    // ─────────────────────────────────────────────
    // TICKET CREATION (Employee)
    // ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:helpdesk_categories,id',
            'subject'     => 'required|string|max:200',
            'description' => 'required|string',
            'priority'    => 'in:low,medium,high,urgent',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx|max:5120',
        ]);

        $employee = auth()->user()->employee;
        $category = HelpdeskCategory::findOrFail($validated['category_id']);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $this->publicFileStore->store($request->file('attachment'), 'helpdesk');
        }

        $ticket = HelpdeskTicket::create([
            'ticket_number'  => $this->generateTicketNumber(),
            'employee_id'    => $employee->id,
            'company_id' => $employee->company_id,
            'category_id'    => $validated['category_id'],
            'subject'        => $validated['subject'],
            'description'    => $validated['description'],
            'priority'       => $validated['priority'] ?? 'medium',
            'attachment_path'=> $attachmentPath,
            'status'         => 'open',
            'due_by'         => now()->addHours($category->sla_hours),
        ]);

        // Auto-assign based on team
        $this->autoAssign($ticket, $category);

        return response()->json(['message' => 'Ticket raised.', 'data' => $ticket->load('category')], 201);
    }

    // ─────────────────────────────────────────────
    // MY TICKETS  (Employee view)
    // ─────────────────────────────────────────────

    public function myTickets(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        $tickets = HelpdeskTicket::where('employee_id', $employee->id)
            ->with(['category', 'assignedTo:id,name'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($tickets);
    }

    // ─────────────────────────────────────────────
    // AGENT VIEW  (HR / IT / Finance team)
    // ─────────────────────────────────────────────

    public function agentTickets(Request $request): JsonResponse
    {
        $this->authorize('helpdesk.view');

        $tickets = HelpdeskTicket::with([
            'employee:id,employee_id,first_name,middle_name,last_name',
            'category',
            'assignedTo:id,name',
        ])
        ->where('company_id', $request->company_id)
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
        ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
        ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
        ->when($request->my_tickets, fn($q) => $q->where('assigned_to', auth()->id()))
        ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
        ->orderBy('due_by')
        ->paginate(25);

        return response()->json($tickets);
    }

    // ─────────────────────────────────────────────
    // TICKET DETAIL
    // ─────────────────────────────────────────────

    public function show(HelpdeskTicket $ticket): JsonResponse
    {
        $ticket->load([
            'employee:id,employee_id,first_name,middle_name,last_name,official_email,mobile',
            'category',
            'assignedTo:id,name',
            'comments' => fn($q) => $q->with('user:id,name')->orderBy('created_at'),
        ]);

        return response()->json(['data' => $ticket]);
    }

    // ─────────────────────────────────────────────
    // COMMENTS / REPLIES
    // ─────────────────────────────────────────────

    public function addComment(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'comment'     => 'required|string',
            'is_internal' => 'sometimes|boolean',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $isInternal   = $validated['is_internal'] ?? false;
        $isEmployee   = auth()->user()->employee?->id === $ticket->employee_id;

        // Employees can only add non-internal comments
        if ($isEmployee) $isInternal = false;

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $this->publicFileStore->store($request->file('attachment'), 'helpdesk/comments');
        }

        $comment = HelpdeskComment::create([
            'ticket_id'       => $ticket->id,
            'user_id'         => auth()->id(),
            'comment'         => $validated['comment'],
            'attachment_path' => $attachmentPath,
            'is_internal'     => $isInternal,
        ]);

        // Update ticket status if agent replied
        if (!$isEmployee && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }
        if (!$isEmployee && !$isInternal) {
            $ticket->update(['status' => 'pending_employee']);
        }

        return response()->json(['message' => 'Comment added.', 'data' => $comment->load('user:id,name')]);
    }

    // ─────────────────────────────────────────────
    // STATUS MANAGEMENT
    // ─────────────────────────────────────────────

    public function assign(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('helpdesk.manage');

        $request->validate(['assigned_to' => 'required|exists:users,id']);

        $ticket->update(['assigned_to' => $request->assigned_to, 'status' => 'in_progress']);

        return response()->json(['message' => 'Ticket assigned.']);
    }

    public function resolve(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        $this->authorize('helpdesk.view');

        $request->validate(['closure_remarks' => 'required|string']);

        $ticket->update([
            'status'          => 'resolved',
            'resolved_at'     => now(),
            'closure_remarks' => $request->closure_remarks,
        ]);

        // Add comment
        HelpdeskComment::create([
            'ticket_id'  => $ticket->id,
            'user_id'    => auth()->id(),
            'comment'    => 'Ticket resolved: ' . $request->closure_remarks,
            'is_internal'=> false,
        ]);

        return response()->json(['message' => 'Ticket resolved.']);
    }

    public function close(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        // Employee closes after satisfaction
        if ($ticket->status !== 'resolved') {
            return response()->json(['message' => 'Only resolved tickets can be closed.'], 422);
        }

        $validated = $request->validate([
            'rating'  => 'nullable|integer|min:1|max:5',
            'remarks' => 'nullable|string',
        ]);

        $ticket->update([
            'status'    => 'closed',
            'rating'    => $validated['rating'] ?? null,
            'closed_at' => now(),
        ]);

        return response()->json(['message' => 'Ticket closed. Thank you for your feedback.']);
    }

    public function reopen(Request $request, HelpdeskTicket $ticket): JsonResponse
    {
        if (!in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json(['message' => 'Only resolved/closed tickets can be reopened.'], 422);
        }

        $request->validate(['reason' => 'required|string']);

        $ticket->update(['status' => 'reopened']);

        HelpdeskComment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => auth()->id(),
            'comment'     => 'Ticket reopened: ' . $request->reason,
            'is_internal' => false,
        ]);

        return response()->json(['message' => 'Ticket reopened.']);
    }

    // ─────────────────────────────────────────────
    // ANALYTICS  (for HR Dashboard)
    // ─────────────────────────────────────────────

    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('helpdesk.view');

        $companyId = $request->company_id;
        $from     = $request->get('from', now()->startOfMonth());
        $to       = $request->get('to', now()->endOfMonth());

        $byStatus = HelpdeskTicket::where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byPriority = HelpdeskTicket::where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $breachedSla = HelpdeskTicket::where('company_id', $companyId)
            ->whereNotIn('status', ['closed'])
            ->where('due_by', '<', now())
            ->count();

        $avgResolution = HelpdeskTicket::where('company_id', $companyId)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        $topCategories = HelpdeskTicket::where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('category_id, COUNT(*) as count')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'by_status'      => $byStatus,
            'by_priority'    => $byPriority,
            'breached_sla'   => $breachedSla,
            'avg_resolution_hours' => round($avgResolution ?? 0, 1),
            'top_categories' => $topCategories,
        ]);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function generateTicketNumber(): string
    {
        $last = HelpdeskTicket::orderByDesc('id')->value('ticket_number');
        $num  = 1;
        if ($last) {
            preg_match('/(\d+)$/', $last, $m);
            $num = (int)($m[1] ?? 0) + 1;
        }
        return 'TKT-' . now()->year . '-' . str_pad($num, 5, '0', STR_PAD_LEFT);
    }

    protected function autoAssign(HelpdeskTicket $ticket, HelpdeskCategory $category): void
    {
        // Find an available agent from the assigned team (basic round-robin)
        // In production, this can use a queue or sophisticated assignment logic
        $teamRoleMap = [
            'hr'      => 'hr_manager',
            'payroll' => 'payroll_admin',
            'it'      => 'it_admin',
            'finance' => 'finance_admin',
            'admin'   => 'admin',
        ];
        $roleName = $teamRoleMap[$category->assigned_team] ?? 'hr_manager';

        $agent = User::whereHas('roles', fn($q) => $q->where('slug', $roleName))
            ->withCount(['assignedTickets' => fn($q) => $q->whereNotIn('status', ['closed', 'resolved'])])
            ->orderBy('assigned_tickets_count')
            ->first();

        if ($agent) {
            $ticket->update(['assigned_to' => $agent->id]);
        }
    }
}
