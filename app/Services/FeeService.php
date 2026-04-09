<?php
namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentDiscount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FeeService {
    public function generateInvoices(int $month, int $year) {
        return DB::transaction(function () use ($month, $year) {
            $settings = SchoolSetting::first();
            $dueDay = $settings ? $settings->fee_due_day : 10;
            $dueDate = Carbon::create($year, $month, $dueDay)->format('Y-m-d');
            
            // First day of invoice month
            $firstDayOfMonth = Carbon::create($year, $month, 1)->format('Y-m-d');
            // Last day of invoice month
            $lastDayOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');

            $currentYear = AcademicYear::where('is_current', 1)->first();
            if (!$currentYear || $currentYear->is_locked) {
                throw new \Exception("Active academic year not found or is locked.");
            }

            $students = Student::where('status', 'active')->with(['class'])->get();
            $feeStructures = FeeStructure::where('academic_year_id', $currentYear->id)
                ->where('frequency', 'monthly')
                ->where('is_active', 1)
                ->get()
                ->groupBy('class_id');

            $generatedCount = 0;

            foreach ($students as $student) {
                // Check if invoice already exists
                $exists = FeeInvoice::where('student_id', $student->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if ($exists) continue;

                $structures = $feeStructures->get($student->class_id) ?? collect();
                $currentCharges = $structures->sum('amount');

                // Get arrears
                $arrears = FeeInvoice::where('student_id', $student->id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->sum('balance');

                // Get active discounts
                $discounts = StudentDiscount::where('student_id', $student->id)
                    ->where('is_active', 1)
                    ->where('valid_from', '<=', $firstDayOfMonth)
                    ->where(function ($query) use ($lastDayOfMonth) {
                        $query->whereNull('valid_until')
                              ->orWhere('valid_until', '>=', $lastDayOfMonth);
                    })
                    ->get();

                $discountAmount = 0;
                $discountBreakdown = [];

                foreach ($discounts as $discount) {
                    $applicableAmount = 0;
                    if ($discount->applies_to == 'all') {
                        $applicableAmount = $currentCharges;
                    } elseif ($discount->applies_to == 'tuition_only') {
                        $applicableAmount = $structures->filter(function($f) {
                            return stripos($f->fee_head, 'tuition') !== false;
                        })->sum('amount');
                    } elseif ($discount->applies_to == 'specific_head') {
                        $applicableAmount = $structures->where('fee_head', $discount->fee_head_name)->sum('amount');
                    }

                    $computedDiscount = 0;
                    if ($discount->discount_type == 'percentage') {
                        $computedDiscount = ($applicableAmount * $discount->discount_value) / 100;
                    } elseif ($discount->discount_type == 'fixed') {
                        $computedDiscount = $discount->discount_value;
                    }

                    $discountAmount += $computedDiscount;
                    $discountBreakdown[] = [
                        'id' => $discount->id,
                        'name' => $discount->discount_name,
                        'amount' => $computedDiscount
                    ];
                }

                // Cap discount
                if ($discountAmount > $currentCharges) {
                    $discountAmount = $currentCharges;
                }

                // Fine is handled by a scheduler, initial fine is 0
                $fine = 0;
                $netAmount = $currentCharges + $arrears - $discountAmount + $fine;
                
                // Safety catch
                if ($netAmount < 0) {
                    $netAmount = 0;
                }

                FeeInvoice::create([
                    'invoice_no' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . uniqid(),
                    'student_id' => $student->id,
                    'academic_year_id' => $currentYear->id,
                    'month' => $month,
                    'year' => $year,
                    'current_charges' => $currentCharges,
                    'arrears' => $arrears,
                    'discount_amount' => $discountAmount,
                    'discount_breakdown' => json_encode($discountBreakdown),
                    'fine' => $fine,
                    'net_amount' => $netAmount,
                    'amount_paid' => 0,
                    'balance' => $netAmount,
                    'due_date' => $dueDate,
                    'status' => $netAmount > 0 ? 'pending' : 'paid',
                ]);

                $generatedCount++;
            }

            return ['success' => true, 'count' => $generatedCount];
        });
    }

    public function recordPayment($invoiceId, $receivedBy, $amount, $method, $refNo = null, $remarks = null) {
        return DB::transaction(function () use ($invoiceId, $receivedBy, $amount, $method, $refNo, $remarks) {
            $invoice = FeeInvoice::lockForUpdate()->findOrFail($invoiceId);
            
            if ($amount <= 0) throw new \Exception("Payment amount must be greater than zero.");
            
            $paymentDate = now()->format('Y-m-d');
            $receiptNo = 'REC-' . now()->format('Y-m-') . uniqid();

            $payment = FeePayment::create([
                'invoice_id' => $invoice->id,
                'received_by' => $receivedBy,
                'amount_paid' => $amount,
                'payment_method' => $method,
                'reference_no' => $refNo,
                'payment_date' => $paymentDate,
                'remarks' => $remarks,
                'receipt_no' => $receiptNo,
            ]);

            $invoice->amount_paid += $amount;
            $invoice->balance = $invoice->net_amount - $invoice->amount_paid;

            if ($invoice->balance <= 0) {
                // Overpayment isn't explicitly requested to be handled as advance, we'll clamp or leave as negative depending on school preference. MVP logic: Just mark paid.
                $invoice->status = 'paid';
            } else {
                $invoice->status = 'partial';
            }
            
            $invoice->save();

            // Notify WhatsApp later using controller or observer
            return $payment;
        });
    }
}
