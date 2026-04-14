<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FeeService;

class FeePaymentController extends Controller
{
    protected $feeService;

    public function __construct(FeeService $feeService) {
        $this->feeService = $feeService;
    }

    public function index(Request $request) {
        $query = \App\Models\FeePayment::with(['invoice.student.class']);

        // Filtering by payment date (standard for financial history)
        if ($request->filled('month')) {
            $query->whereMonth('payment_date', $request->month);
        }
        if ($request->filled('year')) {
            $query->whereYear('payment_date', $request->year);
        }
        if ($request->filled('class_id') && $request->class_id !== 'null') {
            $query->whereHas('invoice.student', function ($q) use ($request) {
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
                  ->orWhereHas('invoice.student', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Get total for stats (clone query to avoid side effects)
        $statQuery = clone $query;
        $stats = [
            'total_amount' => (float) $statQuery->sum('amount_paid'),
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
        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,online',
            'payment_date' => 'nullable|date',
            'remarks' => 'nullable|string'
        ]);

        try {
            $payment = $this->feeService->recordPayment(
                $id,
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
}
