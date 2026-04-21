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
            
            // Find the academic year that covers this billing month
            $billingStart = Carbon::create($year, $month, 1)->startOfMonth();
            $targetYear = AcademicYear::where('start_date', '<=', $billingStart)
                                      ->where('end_date', '>=', $billingStart)
                                      ->first();
                                      
            if (!$targetYear) {
                // Fallback to current year if dates are not properly set, or throw error
                $targetYear = AcademicYear::where('is_current', 1)->first();
                if (!$targetYear) {
                    throw new \Exception("Active academic year not found.");
                }
            }

            $query = Student::where('status', 'active')->with(['class']);
            if (!empty($classIds)) {
                $query->whereIn('class_id', $classIds);
            }
            $students = $query->get();

            // Fetch structures. We'll handle different frequencies.
            $allStructures = FeeStructure::where('academic_year_id', $targetYear->id)
                ->where('is_active', 1)
                ->get()
                ->groupBy('class_id');
                
            if ($allStructures->isEmpty()) {
                throw new \Exception("We don't have any fee structures defined for this month.");
            }

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
                $currentCharges = $classStructures->where('frequency', 'monthly')->sum('amount');
                
                $additionalHeads = [];
                $additionalChargesTotal = 0;

                // ONE-TIME FEES (Admission)
                if ($includeAdmission) {
                    $hasAnyPriorInvoice = FeeInvoice::where('student_id', $student->id)->exists();
                    if (!$hasAnyPriorInvoice) {
                        $oneTimeFees = $classStructures->where('frequency', 'one_time');
                        foreach ($oneTimeFees as $fs) {
                            $additionalChargesTotal += $fs->amount;
                            $additionalHeads[] = ['name' => $fs->fee_head, 'amount' => $fs->amount, 'period' => 'one_time'];
                        }
                    }
                }

                // YEARLY FEES (Annual Fund)
                if ($includeAnnual) {
                    $hasInvoicesThisYear = FeeInvoice::where('student_id', $student->id)
                        ->where('academic_year_id', $targetYear->id)
                        ->exists();
                    if (!$hasInvoicesThisYear) {
                        $yearlyFees = $classStructures->where('frequency', 'yearly');
                        foreach ($yearlyFees as $fs) {
                            $additionalChargesTotal += $fs->amount;
                            $additionalHeads[] = ['name' => $fs->fee_head, 'amount' => $fs->amount, 'period' => 'yearly'];
                        }
                    }
                }

                // Arrears are now handled by the ledger system (allocations), not at generation time.

                // Get active discounts
                $discounts = StudentDiscount::where('student_id', $student->id)
                    ->where('is_active', 1)
                    ->get();

                $discountAmount = 0;
                $discountBreakdown = [];

                foreach ($discounts as $discount) {
                    $applicableAmount = 0;
                    if ($discount->applies_to == 'all') {
                        $applicableAmount = $currentCharges + $additionalChargesTotal;
                    } elseif ($discount->applies_to == 'tuition_only') {
                        // We assumes monthly fees usually contain tuition
                        $applicableAmount = $classStructures->filter(function($f) {
                            return stripos($f->fee_head, 'tuition') !== false && $f->frequency == 'monthly';
                        })->sum('amount');
                    } elseif ($discount->applies_to == 'specific_head') {
                        $applicableAmount = $classStructures->where('fee_head', $discount->fee_head_name)->sum('amount');
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

                $grossAmount = $currentCharges + $additionalChargesTotal;

                if ($discountAmount > $grossAmount) {
                    $discountAmount = $grossAmount;
                }

                // Late Fee Calculation if generating for past month
                $fine = 0;
                if ($isOverdueGeneration && $settings && $settings->late_fine_per_month > 0) {
                    $fine = (float) $settings->late_fine_per_month;
                }

                $netAmount = $grossAmount - $discountAmount + $fine;
                if ($netAmount < 0) $netAmount = 0;

                // Skip generating 0/empty vouchers for students who have NO structures applied
                if ($grossAmount <= 0 && $fine <= 0) {
                    continue;
                }

                FeeInvoice::create([
                    'invoice_no' => 'INV-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . strtoupper(uniqid()),
                    'student_id' => $student->id,
                    'academic_year_id' => $targetYear->id,
                    'month' => $month,
                    'year' => $year,
                    'current_charges' => $currentCharges,
                    'additional_charges_breakdown' => $additionalHeads,
                    'discount_amount' => $discountAmount,
                    'discount_breakdown' => $discountBreakdown,
                    'fine' => $fine,
                    'net_amount' => $netAmount,
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

            // Arrears restoration logic removed as we are now ledger-based.

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
            if ($invoice->status === 'pending') $invoice->status = 'overdue';
            $invoice->save();
        }

        return $invoice;
    }

    // recordPayment logic moved to FeePaymentService
}
