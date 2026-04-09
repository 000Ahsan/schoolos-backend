<?php
namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\Classes;
use Illuminate\Support\Facades\DB;

class PromotionService {
    public function promoteAll($promotedByUserId, $newYearId) {
        return DB::transaction(function () use ($promotedByUserId, $newYearId) {
            $currentYear = AcademicYear::where('is_current', 1)->first();
            $newYear = AcademicYear::findOrFail($newYearId);

            if (!$currentYear || $currentYear->is_locked) {
                throw new \Exception("Current academic year is not set or is locked.");
            }

            $students = Student::where('status', 'active')->get();
            $classes = Classes::orderBy('numeric_order')->get()->keyBy('numeric_order');

            foreach ($students as $student) {
                $currentClass = $student->class;
                $nextClassOrder = $currentClass->numeric_order + 1;
                $nextClass = $classes->get($nextClassOrder);

                if ($nextClass) {
                    $student->update(['class_id' => $nextClass->id]);
                    StudentPromotion::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $currentYear->id,
                        'from_class_id' => $currentClass->id,
                        'to_class_id' => $nextClass->id,
                        'promoted_by' => $promotedByUserId,
                        'promotion_type' => 'promoted'
                    ]);
                } else {
                    $student->update(['status' => 'graduated']);
                    StudentPromotion::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $currentYear->id,
                        'from_class_id' => $currentClass->id,
                        'to_class_id' => null,
                        'promoted_by' => $promotedByUserId,
                        'promotion_type' => 'graduated'
                    ]);
                }
            }

            // Deactivate expired discounts
            \App\Models\StudentDiscount::where('is_active', 1)
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', $newYear->start_date)
                ->update(['is_active' => 0]);

            $currentYear->update(['is_locked' => 1, 'is_current' => 0]);
            $newYear->update(['is_current' => 1]);

            return ['success' => true, 'count' => $students->count()];
        });
    }
}
