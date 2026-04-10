<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\FeeStructure;
use Illuminate\Database\Seeder;

class FeeStructureSeeder extends Seeder
{
    public function run(): void
    {
        $currentYear = AcademicYear::where('is_current', true)->first();
        if (!$currentYear) return;

        $classes = Classes::all();

        foreach ($classes as $class) {
            // Admission Fee (One time)
            FeeStructure::updateOrCreate(
                [
                    'class_id' => $class->id,
                    'academic_year_id' => $currentYear->id,
                    'fee_head' => 'Admission Fee'
                ],
                [
                    'amount' => 5000.00,
                    'frequency' => 'one_time',
                ]
            );

            // Tuition Fee (Monthly)
            $tuitionAmount = 2000 + ($class->numeric_order * 200);
            FeeStructure::updateOrCreate(
                [
                    'class_id' => $class->id,
                    'academic_year_id' => $currentYear->id,
                    'fee_head' => 'Tuition Fee'
                ],
                [
                    'amount' => $tuitionAmount,
                    'frequency' => 'monthly',
                ]
            );

            // Annual Fund (Annually)
            FeeStructure::updateOrCreate(
                [
                    'class_id' => $class->id,
                    'academic_year_id' => $currentYear->id,
                    'fee_head' => 'Annual Fund'
                ],
                [
                    'amount' => 3000.00,
                    'frequency' => 'annually',
                ]
            );
        }
    }
}
