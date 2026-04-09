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
        $query = FeeInvoice::with(['student']);
        
        if ($request->has('month')) $query->where('month', $request->month);
        if ($request->has('year')) $query->where('year', $request->year);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('student_id')) $query->where('student_id', $request->student_id);

        return response()->json($query->get());
    }

    public function generate(Request $request) {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        try {
            $result = $this->feeService->generateInvoices($request->month, $request->year);
            return response()->json([
                'message' => "Successfully generated {$result['count']} invoices."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($id) {
        $invoice = FeeInvoice::with(['student', 'payments'])->findOrFail($id);
        return response()->json($invoice);
    }
}
