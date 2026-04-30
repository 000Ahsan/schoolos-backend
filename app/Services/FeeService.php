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
            $calculationMode = $settings->fee_calculation_mode ?? 'fixed_month';
            
            $defaultDueDay = $settings ? $settings->fee_due_day : 10;
            $defaultDueDate = Carbon::create($year, $month, $defaultDueDay);
            $isOverdueGeneration = now()->greaterThan($defaultDueDate);
            
            // Find the academic year that covers this billing month
            $billingStart = Carbon::create($year, $month, 1)->startOfMonth();
            $targetYear = AcademicYear::where('start_date', '<=', $billingStart)
                                      ->where('end_date', '>=', $billingStart)
                                      ->first();
                                      
            if (!$targetYear) {
                throw new \Exception("No academic year defined covering {$month}/{$year}. Please check your Academic Year settings.");
            }

            $billingEnd = Carbon::create($year, $month, 1)->endOfMonth();
            $query = Student::where('status', 'active')
                            ->where('admission_date', '<=', $billingEnd)
                            ->with(['class']);
            if (!empty($classIds)) {
                $query->whereIn('class_id', $classIds);
            }
            $students = $query->get();

            // Fetch structures for the target year and classes
            $structuresQuery = FeeStructure::where('academic_year_id', $targetYear->id)
                ->where('is_active', 1);
            
            if (!empty($classIds)) {
                $structuresQuery->whereIn('class_id', $classIds);
            }

            $allStructures = $structuresQuery->get()->groupBy('class_id');
                
            if ($allStructures->isEmpty()) {
                throw new \Exception("No active fee structures found for the selected classes/month. Please define fee structures first.");
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

                // Skip generating 0/empty vouchers for students who have NO charges applied for this month
                if ($grossAmount <= 0) {
                    continue;
                }

                // Calculate Due Date for this specific student/month
                $studentDueDate = $defaultDueDate;
                if ($calculationMode === 'admission_anniversary' && $student->admission_date) {
                    $admissionDay = Carbon::parse($student->admission_date)->day;
                    $dueOffset = $settings ? (int)$settings->fee_due_day : 0;
                    
                    // Create base anniversary date in the target month/year
                    $baseAnniversary = Carbon::create($year, $month, $admissionDay);
                    
                    // Handle months with fewer days (e.g., Feb 31 -> Feb 28/29)
                    if ($baseAnniversary->month != $month) {
                        $baseAnniversary = Carbon::create($year, $month, 1)->endOfMonth();
                    }
                    
                    // Add the due day offset as requested: admission date + due date days
                    $studentDueDate = $baseAnniversary->addDays($dueOffset);
                }

                $isStudentOverdue = now()->greaterThan($studentDueDate);

                // Late Fee Calculation - only applied if there are base charges
                $fine = 0;
                if ($isStudentOverdue && $settings && $settings->late_fine_per_month > 0) {
                    $fine = (float) $settings->late_fine_per_month;
                }

                $netAmount = $grossAmount - $discountAmount + $fine;
                if ($netAmount < 0) $netAmount = 0;

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
                    'due_date' => $studentDueDate->format('Y-m-d'),
                    'status' => $netAmount <= 0 ? 'paid' : ($isStudentOverdue ? 'overdue' : 'pending'),
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
