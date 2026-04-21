<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeInvoice;
use App\Services\FeeService;

class FeeInvoiceController extends Controller
{
    protected $feeService;

    public function __construct(FeeService $feeService) {
        $this->feeService = $feeService;
    }

    public function index(Request $request) {
        $query = FeeInvoice::with(['student.class']);
        
        // Search by Invoice No, Student Name or Roll No
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('student', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('roll_no', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('class_id')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('month')) $query->where('month', $request->month);
        if ($request->filled('year')) $query->where('year', $request->year);

        $query->with(['student' => function($q) {
            $q->withSum(['invoices as total_charged' => function($q) {
                $q->where('status', '!=', 'waived');
            }], 'net_amount')
            ->withSum('allocations as total_paid', 'allocated_amount');
        }]);
        
        $query->withSum('allocations as paid_amount', 'allocated_amount');

        $results = $query->orderByDesc('created_at')
                        ->paginate($request->get('limit', 100));

        $results->through(function($invoice) {
            $invoice->amount_paid = (float)($invoice->paid_amount ?? 0);
            $invoice->balance = (float)$invoice->net_amount - $invoice->amount_paid;
            
            // Global student balance (Total Charged - Total Paid)
            if ($invoice->student) {
                $invoice->student_total_due = (float)($invoice->student->total_charged ?? 0) - (float)($invoice->student->total_paid ?? 0);
            } else {
                $invoice->student_total_due = $invoice->balance;
            }
            
            return $invoice;
        });

        return response()->json($results);
    }

    public function generate(Request $request) {
        // Handle billing_month format (YYYY-MM) from frontend
        if ($request->has('billing_month')) {
            $parts = explode('-', $request->billing_month);
            $request->merge([
                'year' => (int)$parts[0],
                'month' => (int)$parts[1]
            ]);
        }

        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'classes' => 'nullable|array',
            'classes.*' => 'exists:classes,id',
            'include_admission' => 'boolean',
            'include_annual' => 'boolean'
        ]);

        // Restrict future month generation
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        
        if ($request->year > $currentYear || ($request->year == $currentYear && $request->month > $currentMonth)) {
            return response()->json(['error' => 'You cannot generate vouchers for future months.'], 400);
        }

        try {
            $result = $this->feeService->generateInvoices(
                $request->month, 
                $request->year, 
                $request->get('classes', []),
                $request->get('include_admission', true),
                $request->get('include_annual', true)
            );
            return response()->json([
                'message' => "Successfully generated {$result['count']} invoices."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id) {
        $invoice = FeeInvoice::with(['student.class', 'payments'])
            ->withSum('allocations as paid_amount', 'allocated_amount')
            ->findOrFail($id);
        
        // Add balance for frontend compatibility
        $invoice->paid_amount = (float)($invoice->paid_amount ?? 0);
        $invoice->balance = (float)$invoice->net_amount - $invoice->paid_amount;
        
        // Fetch all other unpaid invoices (Arrears)
        $arrears = FeeInvoice::where('student_id', $invoice->student_id)
            ->where('id', '!=', $id)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->withSum('allocations as paid_amount', 'allocated_amount')
            ->get()
            ->map(function($inv) {
                $inv->paid_amount = (float)($inv->paid_amount ?? 0);
                $inv->balance = (float)$inv->net_amount - $inv->paid_amount;
                return $inv;
            });
        
        $invoice->previous_arrears = $arrears;
        $invoice->total_payable = $invoice->balance + $arrears->sum('balance');

        $this->feeService->checkAndApplyFine($invoice);
        return response()->json($invoice);
    }

    public function destroy($id) {
        try {
            $this->feeService->deleteInvoice($id);
            return response()->json(['message' => 'Invoice deleted successfully. History restored.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function ledger($studentId) {
        $invoices = FeeInvoice::where('student_id', $studentId)
            ->withSum('allocations as paid_amount', 'allocated_amount')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $ledgerInvoices = $invoices->map(function($invoice) {
            $paid = (float)($invoice->paid_amount ?? 0);
            return [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'month' => $invoice->month,
                'year' => $invoice->year,
                'amount' => (float)$invoice->net_amount,
                'paid' => $paid,
                'balance' => (float)$invoice->net_amount - $paid,
                'status' => strtoupper($invoice->status)
            ];
        });

        $totalOutstanding = $invoices->sum(function($invoice) {
            return (float)$invoice->net_amount - (float)($invoice->paid_amount ?? 0);
        });

        return response()->json([
            'invoices' => $ledgerInvoices,
            'total_outstanding' => $totalOutstanding
        ]);
    }
}
