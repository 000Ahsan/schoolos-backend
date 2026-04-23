<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\FeeInvoice;
use App\Jobs\SendBulkFeeRemindersJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeDefaulterController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');
        $classId = $request->get('class_id');

        $today = now()->format('Y-m-d');

        // Main Query: Students who have at least one invoice that is overdue and not paid
        $query = Student::with(['class', 'invoices' => function($q) use ($today) {
                $q->whereIn('status', ['pending', 'overdue', 'partial'])
                  ->where('due_date', '<', $today)
                  ->withSum('allocations', 'allocated_amount');
            }])
            ->whereHas('invoices', function($q) use ($today) {
                $q->whereIn('status', ['pending', 'overdue', 'partial'])
                  ->where('due_date', '<', $today);
            });

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('roll_no', 'like', "%{$search}%");
            });
        }

        if ($classId && $classId !== 'all' && $classId !== 'null') {
            $query->where('class_id', $classId);
        }

        $students = $query->paginate($perPage);

        // Transform results to include calculated totals
        $students->getCollection()->transform(function($student) {
            $overdueInvoices = $student->invoices;
            
            $totalDue = 0;
            $overdueCount = 0;

            foreach ($overdueInvoices as $invoice) {
                // In my refactored show() and ledger() methods, I calculate balance as net_amount - allocations_sum_allocated_amount.
                // Since I already have allocations loaded (assuming I will add them to the query), I'll sum them.
                // Actually, I'll use withSum in the main query for efficiency.
                $paid = (float) $invoice->allocations_sum_allocated_amount;
                $balance = (float) $invoice->net_amount - $paid;
                
                if ($balance > 0) {
                    $totalDue += $balance;
                    $overdueCount++;
                }
            }
            
            return [
                'id' => $student->id,
                'name' => $student->name,
                'roll_no' => $student->roll_no,
                'class_name' => $student->class ? $student->class->name . ' - ' . $student->class->section : 'N/A',
                'guardian_phone' => $student->guardian_phone,
                'unpaid_count' => $overdueCount,
                'total_due' => $totalDue,
            ];
        });

        return response()->json([
            'defaulters' => $students
        ]);
    }

    public function show($id)
    {
        $today = now()->format('Y-m-d');
        $student = Student::with(['invoices' => function($q) use ($today) {
            $q->whereIn('status', ['pending', 'overdue', 'partial'])
              ->where('due_date', '<', $today)
              ->withSum('allocations', 'allocated_amount')
              ->orderBy('due_date', 'asc');
        }])->findOrFail($id);

        $student->invoices->each(function($invoice) {
            $invoice->paid_amount = (float)($invoice->allocations_sum_allocated_amount ?? 0);
            $invoice->balance = (float)$invoice->net_amount - $invoice->paid_amount;
        });

        return response()->json([
            'student' => $student,
            'invoices' => $student->invoices
        ]);
    }

    public function sendBulkReminders(Request $request)
    {
        $studentIds = $request->get('student_ids', []); // Array of selected IDs
        
        if (empty($studentIds)) {
            // If nothing selected, maybe they mean "filtered" or "all"
            // But usually we expect specific selection
            return response()->json(['message' => 'No students selected'], 400);
        }

        // Dispatch bulk job
        SendBulkFeeRemindersJob::dispatch($studentIds);

        return response()->json(['message' => 'Reminders are being sent in the background.']);
    }
}
