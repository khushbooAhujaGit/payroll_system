<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Entity;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskComment;
use App\Models\HelpdeskTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebHelpdeskController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('helpdesk_view');

        $companyId = $this->resolveCompanyId($request);

        $tickets = HelpdeskTicket::with([
            'employee:id,employee_id,first_name,middle_name,last_name',
            'category:id,name',
            'assignedTo:id,name',
        ])
        ->where('company_id', $companyId)
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
        ->orderByDesc('created_at')
        ->paginate(25)->withQueryString();

        $categories = HelpdeskCategory::where('company_id', $companyId)->where('is_active', true)->get();

        $stats = [
            'open'        => HelpdeskTicket::where('company_id', $companyId)->where('status', 'open')->count(),
            'in_progress' => HelpdeskTicket::where('company_id', $companyId)->where('status', 'in_progress')->count(),
            'resolved'    => HelpdeskTicket::where('company_id', $companyId)->where('status', 'resolved')->count(),
        ];

        return view('helpdesk.index', compact('tickets', 'categories', 'stats', 'companyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('helpdesk_raise');

        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:helpdesk_categories,id',
            'subject'     => 'required|string|max:200',
            'description' => 'required|string',
            'priority'    => 'required|in:low,medium,high,urgent',
        ]);

        $employee = auth()->user()->employee;
        $category = HelpdeskCategory::findOrFail($validated['category_id']);
        $num      = 'TKT-' . now()->year . '-' . str_pad(HelpdeskTicket::count() + 1, 5, '0', STR_PAD_LEFT);

        HelpdeskTicket::create([
            'ticket_number' => $num,
            'employee_id'   => $employee?->id,
            'company_id' => $companyId,
            'category_id'   => $validated['category_id'],
            'subject'       => $validated['subject'],
            'description'   => $validated['description'],
            'priority'      => $validated['priority'],
            'status'        => 'open',
            'due_by'        => now()->addHours($category->sla_hours ?? 24),
        ]);

        return redirect()->route('helpdesk')->with('success', 'Ticket raised.');
    }

    public function resolve(Request $request, HelpdeskTicket $ticket)
    {
        $this->authorize('helpdesk_resolve');

        $request->validate(['closure_remarks' => 'required|string']);

        $ticket->update([
            'status'          => 'resolved',
            'resolved_at'     => now(),
            'closure_remarks' => $request->closure_remarks,
        ]);

        HelpdeskComment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => auth()->id(),
            'comment'     => 'Ticket resolved: ' . $request->closure_remarks,
            'is_internal' => false,
        ]);

        return redirect()->route('helpdesk')->with('success', 'Ticket resolved.');
    }

    public function template()
    {
        $this->authorize('helpdesk_view');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tickets');
        foreach (['Employee ID', 'Category', 'Subject', 'Priority (low/medium/high/urgent)', 'Description'] as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        $sheet->fromArray(['EMP0001', 'IT Support', 'Laptop not working', 'high', 'Laptop fails to boot since morning.'], null, 'A2');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'helpdesk_tickets_template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $this->authorize('helpdesk_raise');

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $companyId = $this->resolveCompanyId($request);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            array_shift($rows);

            $count = 0;
            DB::beginTransaction();
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                [$empId, $catName, $subject, $priority, $description] = array_pad($row, 5, null);
                if (!$subject) continue;

                $employee = $empId ? Employee::where('employee_id', $empId)->first() : null;
                $category = $catName ? HelpdeskCategory::where('company_id', $companyId)->where('name', $catName)->first() : null;
                $num      = 'TKT-' . now()->year . '-' . str_pad(HelpdeskTicket::count() + $count + 1, 5, '0', STR_PAD_LEFT);

                HelpdeskTicket::create([
                    'ticket_number' => $num,
                    'employee_id'   => $employee?->id,
                    'company_id' => $companyId,
                    'category_id'   => $category?->id,
                    'subject'       => substr(trim($subject), 0, 200),
                    'description'   => $description ?? '',
                    'priority'      => in_array(strtolower($priority ?? ''), ['low','medium','high','urgent']) ? strtolower($priority) : 'medium',
                    'status'        => 'open',
                    'due_by'        => now()->addHours(24),
                ]);
                $count++;
            }
            DB::commit();
            return redirect()->route('helpdesk')->with('success', "{$count} ticket(s) imported.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('helpdesk')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
