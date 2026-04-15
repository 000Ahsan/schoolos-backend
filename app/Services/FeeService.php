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
    public function generateInvoices(int $month, int $year, array $classIds = [], bool $includeAdmission = true, bool $includeAnnual = true) {
        return DB::transaction(function () use ($month, $year, $classIds, $includeAdmission, $includeAnnual) {
            $settings = SchoolSetting::first();
            $dueDay = $settings ? $settings->fee_due_day : 10;
            $dueDate = Carbon::create($year, $month, $dueDay);
            $isOverdueGeneration = now()->greaterThan($dueDate);
            
            $currentYear = AcademicYear::where('is_current', 1)->first();
            if (!$currentYear) {
                throw new \Exception("Active academic year not found.");
            }

            $query = Student::where('status', 'active')->with(['class']);
            if (!empty($classIds)) {
                $query->whereIn('class_id', $classIds);
            }
            $students = $query->get();

            // Fetch structures. We'll handle different frequencies.
            $allStructures = FeeStructure::where('academic_year_id', $currentYear->id)
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

                $classStructures = $allStructures->get($student->class_id) ?? collect();
                
                // Base: Monthly Charges
                $currentCharges = $classStructures->where('period', 'monthly')->sum('amount');
                
                $additionalHeads = [];

                // ONE-TIME FEES (Admission)
                if ($includeAdmission) {
                    $hasAnyPriorInvoice = FeeInvoice::where('student_id', $student->id)->exists();
                    if (!$hasAnyPriorInvoice) {
                        $oneTimeFees = $classStructures->where('period', 'one_time');
                        foreach ($oneTimeFees as $fs) {
                            $currentCharges += $fs->amount;
                            $additionalHeads[] = ['name' => $fs->fee_head_name, 'amount' => $fs->amount, 'period' => 'one_time'];
                        }
                    }
                }

                // YEARLY FEES (Annual Fund)
                if ($includeAnnual) {
                    $hasInvoicesThisYear = FeeInvoice::where('student_id', $student->id)
                        ->where('academic_year_id', $currentYear->id)
                        ->exists();
                    if (!$hasInvoicesThisYear) {
                        $yearlyFees = $classStructures->where('period', 'yearly');
                        foreach ($yearlyFees as $fs) {
                            $currentCharges += $fs->amount;
                            $additionalHeads[] = ['name' => $fs->fee_head_name, 'amount' => $fs->amount, 'period' => 'yearly'];
                        }
                    }
                }

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

                // Mark previous invoices as carried_forward
                if ($arrears > 0) {
                    $arrearsQuery->update(['status' => 'carried_forward']);
                }

                // Get active discounts
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
                        // We assumes monthly fees usually contain tuition
                        $applicableAmount = $classStructures->filter(function($f) {
                            return stripos($f->fee_head_name, 'tuition') !== false && $f->period == 'monthly';
                        })->sum('amount');
                    } elseif ($discount->applies_to == 'specific_head') {
                        $applicableAmount = $classStructures->where('fee_head_name', $discount->fee_head_name)->sum('amount');
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

                // Late Fee Calculation if generating for past month
                $fine = 0;
                if ($isOverdueGeneration && $settings && $settings->late_fine_per_month > 0) {
                    $fine = (float) $settings->late_fine_per_month;
                }

                $netAmount = $currentCharges + $arrears - $discountAmount + $fine;
                if ($netAmount < 0) $netAmount = 0;

                FeeInvoice::create([
                    'invoice_no' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . strtoupper(uniqid()),
                    'student_id' => $student->id,
                    'academic_year_id' => $currentYear->id,
                    'month' => $month,
                    'year' => $year,
                    'current_charges' => $currentCharges,
                    'arrears' => $arrears,
                    'discount_amount' => $discountAmount,
                    'discount_breakdown' => $discountBreakdown,
                    'fine' => $fine,
                    'net_amount' => $netAmount,
                    'amount_paid' => 0,
                    'balance' => $netAmount,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'status' => $netAmount <= 0 ? 'paid' : ($isOverdueGeneration ? 'overdue' : 'pending'),
                ]);

                $generatedCount++;
            }
            return ['success' => true, 'count' => $generatedCount];
        });
    }

    public function deleteInvoice($id) {
        return DB::transaction(function () use ($id) {
            $invoice = FeeInvoice::lockForUpdate()->findOrFail($id);
            
            if ($invoice->status !== 'pending' && $invoice->status !== 'overdue') {
                throw new \Exception("Only pending or overdue invoices can be deleted. Please reverse payments first if needed.");
            }

            if ($invoice->amount_paid > 0) {
                throw new \Exception("Cannot delete invoice with recorded payments.");
            }

            // If this invoice had arrears, we need to "re-open" the previous carried_forward invoices
            if ($invoice->arrears > 0) {
                FeeInvoice::where('student_id', $invoice->student_id)
                    ->where('status', 'carried_forward')
                    ->where(function($q) use ($invoice) {
                        $q->where('year', '<', $invoice->year)
                          ->orWhere(function($sq) use ($invoice) {
                              $sq->where('year', $invoice->year)->where('month', '<', $invoice->month);
                          });
                    })
                    ->each(function($oldInvoice) {
                        // Revert status based on payment progress and due date
                        if ($oldInvoice->amount_paid > 0) {
                            $oldInvoice->status = 'partial';
                        } else {
                            $dueDate = Carbon::parse($oldInvoice->due_date);
                            $oldInvoice->status = now()->greaterThan($dueDate) ? 'overdue' : 'pending';
                        }
                        $oldInvoice->save();
                    });
            }

            $invoice->delete();
            return true;
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
