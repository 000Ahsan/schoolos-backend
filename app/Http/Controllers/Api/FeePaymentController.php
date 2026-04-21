<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FeeService;

class FeePaymentController extends Controller
{
    protected $feeService;
    protected $feePaymentService;

    public function __construct(FeeService $feeService, \App\Services\FeePaymentService $feePaymentService) {
        $this->feeService = $feeService;
        $this->feePaymentService = $feePaymentService;
    }

    public function index(Request $request) {
        $query = \App\Models\FeePayment::with(['student.class', 'allocations.invoice']);

        // Filtering by payment date (standard for financial history)
        if ($request->filled('month')) {
            $query->whereMonth('payment_date', $request->month);
        }
        if ($request->filled('year')) {
            $query->whereYear('payment_date', $request->year);
        }
        if ($request->filled('class_id') && $request->class_id !== 'null') {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }
        if ($request->filled('student_id') && $request->student_id !== 'null') {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                  ->orWhereHas('student', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Get total for stats (clone query to avoid side effects)
        $statQuery = clone $query;
        $stats = [
            'total_amount' => (float) $statQuery->sum('total_amount'),
            'count' => $statQuery->count()
        ];

        $payments = $query->orderByDesc('payment_date')
                          ->orderByDesc('id')
                          ->paginate($request->per_page ?? 25)
                          ->withQueryString();

        return response()->json([
            'payments' => $payments,
            'stats' => $stats
        ]);
    }

    public function store(Request $request, $id) {
        // Here $id can be invoice_id or student_id. For compatibility with current frontend, 
        // we'll assume it's an invoice_id and find the student.
        $studentId = $request->get('student_id');
        
        if (!$studentId) {
            $invoice = \App\Models\FeeInvoice::find($id);
            if (!$invoice) return response()->json(['error' => 'Invoice or Student not found.'], 404);
            $studentId = $invoice->student_id;
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,online',
            'payment_date' => 'nullable|date',
            'remarks' => 'nullable|string'
        ]);

        try {
            $payment = $this->feePaymentService->recordPayment(
                $studentId,
                $request->user()->id,
                $validated['amount_paid'],
                $validated['payment_method'],
                $request->reference_no,
                $validated['remarks'],
                $validated['payment_date']
            );
            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id) {
        $payment = \App\Models\FeePayment::with(['student.class', 'allocations.invoice', 'receiver'])
            ->findOrFail($id);
            
        return response()->json($payment);
    }
}
