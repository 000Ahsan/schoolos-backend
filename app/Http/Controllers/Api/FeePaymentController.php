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

    public function store(Request $request) {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:fee_invoices,id',
            'amount_paid' => 'required|numeric|min:1',
            'method' => 'required|in:cash,bank_transfer,cheque,online'
        ]);

        try {
            $payment = $this->feeService->recordPayment(
                $validated['invoice_id'],
                $request->user()->id,
                $validated['amount_paid'],
                $validated['method']
            );
            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
