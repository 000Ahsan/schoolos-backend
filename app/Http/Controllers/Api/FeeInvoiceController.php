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

        return response()->json($query->orderByDesc('created_at')->paginate($request->get('limit', 10)));
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
            'classes.*' => 'exists:classes,id'
        ]);

        try {
            $result = $this->feeService->generateInvoices(
                $request->month, 
                $request->year, 
                $request->get('classes', [])
            );
            return response()->json([
                'message' => "Successfully generated {$result['count']} invoices."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id) {
        $invoice = FeeInvoice::with(['student.class', 'payments'])->findOrFail($id);
        $this->feeService->checkAndApplyFine($invoice);
        return response()->json($invoice);
    }
}
