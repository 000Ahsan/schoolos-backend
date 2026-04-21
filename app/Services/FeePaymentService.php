<?php

namespace App\Services;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FeePaymentService
{
    public function recordPayment(int $studentId, int $receivedBy, float $amount, string $method, ?string $refNo = null, ?string $remarks = null, ?string $date = null): FeePayment
    {
        return DB::transaction(function () use ($studentId, $receivedBy, $amount, $method, $refNo, $remarks, $date) {
            
            $paymentDate = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');
            $receiptNo = 'REC-' . now()->format('Y-m-') . strtoupper(uniqid());

            $payment = FeePayment::create([
                'student_id' => $studentId,
                'received_by' => $receivedBy,
                'total_amount' => $amount,
                'payment_method' => $method,
                'reference_no' => $refNo,
                'payment_date' => $paymentDate,
                'remarks' => $remarks,
                'receipt_no' => $receiptNo,
            ]);

            $remaining = (float) $amount;

            // Fetch unpaid invoices for the student (FIFO)
            // We use withSum and having logic to identify which invoices still have a balance
            $invoices = FeeInvoice::where('student_id', $studentId)
                ->withSum('allocations as paid_amount', 'allocated_amount')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) break;

                $paid = (float) ($invoice->paid_amount ?? 0);
                $due = (float) ($invoice->net_amount - $paid);

                if ($due <= 0) continue;

                $allocate = min($remaining, $due);

                FeePaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => $allocate,
                ]);

                $remaining -= $allocate;

                // Update invoice status based on total allocation
                $totalPaidAfter = $paid + $allocate;
                if ($totalPaidAfter >= $invoice->net_amount) {
                    $invoice->status = 'paid';
                } else if ($totalPaidAfter > 0) {
                    $invoice->status = 'partial';
                }
                $invoice->save();
            }

            return $payment;
        });
    }
}
