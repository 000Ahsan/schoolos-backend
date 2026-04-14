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
    public function generateInvoices(int $month, int $year, array $classIds = []) {
        return DB::transaction(function () use ($month, $year, $classIds) {
            $settings = SchoolSetting::first();
            $dueDay = $settings ? $settings->fee_due_day : 10;
            $dueDate = Carbon::create($year, $month, $dueDay)->format('Y-m-d');
            
            $currentYear = AcademicYear::where('is_current', 1)->first();
            if (!$currentYear || $currentYear->is_locked) {
                throw new \Exception("Active academic year not found or is locked.");
            }

            $query = Student::where('status', 'active')->with(['class']);
            if (!empty($classIds)) {
                $query->whereIn('class_id', $classIds);
            }
            $students = $query->get();

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

                // Get arrears (unpaid balances from previous months)
                $arrearsQuery = FeeInvoice::where('student_id', $student->id)
                    ->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->where(function($q) use ($month, $year) {
                        $q->where('year', '<', $year)
                          ->orWhere(function($sq) use ($month, $year) {
                              $sq->where('year', $year)->where('month', '<', $month);
                          });
                    });

                $arrears = $arrearsQuery->sum('balance');

                // Mark previous invoices as carried_forward to clean up records
                if ($arrears > 0) {
                    $arrearsQuery->update(['status' => 'carried_forward']);
                }

                // Get active discounts - Removed date filters as they were removed from the schema
                $discounts = StudentDiscount::where('student_id', $student->id)
                    ->where('is_active', 1)
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
                        'amount' => $computedDiscount,
                        'type' => $discount->discount_type,
                        'value' => $discount->discount_value
                    ];
                }

                if ($discountAmount > $currentCharges) {
                    $discountAmount = $currentCharges;
                }

                $fine = 0;
                $netAmount = $currentCharges + $arrears - $discountAmount + $fine;
                
                if ($netAmount < 0) $netAmount = 0;

                FeeInvoice::create([
                    'invoice_no' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . uniqid(),
                    'student_id' => $student->id,
                    'academic_year_id' => $currentYear->id,
                    'month' => $month,
                    'year' => $year,
                    'current_charges' => $currentCharges,
                    'arrears' => $arrears,
                    'discount_amount' => $discountAmount,
                    'discount_breakdown' => $discountBreakdown, // JSON cast handled by model
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

    public function checkAndApplyFine(FeeInvoice $invoice) {
        if ($invoice->status === 'paid' || $invoice->fine > 0) return $invoice;

        $settings = SchoolSetting::first();
        if (!$settings || $settings->late_fine_per_month <= 0) return $invoice;

        $dueDate = Carbon::parse($invoice->due_date);
        if (now()->greaterThan($dueDate)) {
            $fine = (float) $settings->late_fine_per_month;
            $invoice->fine = $fine;
            $invoice->net_amount += $fine;
            $invoice->balance += $fine;
            if ($invoice->status === 'pending') $invoice->status = 'overdue';
            $invoice->save();
        }

        return $invoice;
    }

    public function recordPayment($invoiceId, $receivedBy, $amount, $method, $refNo = null, $remarks = null, $date = null) {
        return DB::transaction(function () use ($invoiceId, $receivedBy, $amount, $method, $refNo, $remarks, $date) {
            $invoice = FeeInvoice::lockForUpdate()->findOrFail($invoiceId);
            if ($amount <= 0) throw new \Exception("Payment amount must be greater than zero.");
            
            $paymentDate = $date ?? now()->format('Y-m-d');
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
            $statusBefore = $invoice->status;
            $invoice->status = $invoice->balance <= 0 ? 'paid' : 'partial';
            $invoice->save();

            // Handle Arrears Allocation: Update older carried_foward invoices
            if ($invoice->arrears > 0) {
                $remainingToAllocate = $amount;
                
                $carriedForwardInvoices = FeeInvoice::where('student_id', $invoice->student_id)
                    ->where('status', 'carried_forward')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();

                foreach ($carriedForwardInvoices as $oldInvoice) {
                    if ($remainingToAllocate <= 0) break;

                    $toApply = min($remainingToAllocate, $oldInvoice->balance);
                    $oldInvoice->amount_paid += $toApply;
                    $oldInvoice->balance -= $toApply;
                    
                    if ($oldInvoice->balance <= 0) {
                        $oldInvoice->status = 'paid';
                    }
                    $oldInvoice->save();
                    
                    $remainingToAllocate -= $toApply;
                }
            }

            return $payment;
        });
    }
}
