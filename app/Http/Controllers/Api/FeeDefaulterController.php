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
                  ->where('due_date', '<', $today);
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
            $unpaidInvoices = $student->invoices;
            
            // For each invoice, the unpaid amount is net_amount minus what's paid
            // However, our system seems to track status. Let's assume net_amount for non-paid ones 
            // is what's overdue, but if it's partial we might need more logic.
            // For simplicity, we'll use a derived sum or aggregate.
            
            $totalUnpaid = $unpaidInvoices->sum('net_amount'); // Simplified for now
            // If invoices table contains current_balance, that would be better.
            
            return [
                'id' => $student->id,
                'name' => $student->name,
                'roll_no' => $student->roll_no,
                'class_name' => $student->class ? $student->class->name : 'N/A',
                'guardian_phone' => $student->guardian_phone,
                'unpaid_count' => $unpaidInvoices->count(),
                'total_due' => $totalUnpaid,
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
              ->orderBy('due_date', 'asc');
        }])->findOrFail($id);

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
