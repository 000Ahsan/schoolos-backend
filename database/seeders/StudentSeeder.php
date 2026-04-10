<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $classes = Classes::all();

        if ($classes->isEmpty()) return;

        $students = [
            ['name' => 'Ahmed Khan', 'father_name' => 'Bilal Khan', 'gender' => 'male'],
            ['name' => 'Sara Ali', 'father_name' => 'Ali Raza', 'gender' => 'female'],
            ['name' => 'Zainab Fatima', 'father_name' => 'Muhammad Usman', 'gender' => 'female'],
            ['name' => 'Umer Farooq', 'father_name' => 'Farooq Ahmed', 'gender' => 'male'],
            ['name' => 'Ayesha Siddiqui', 'father_name' => 'Siddiq Jan', 'gender' => 'female'],
        ];

        $rollCounter = 1001;

        foreach ($classes as $class) {
            foreach ($students as $studentData) {
                Student::updateOrCreate(
                    ['roll_no' => 'ROLL-' . $rollCounter],
                    [
                        'name' => $studentData['name'],
                        'father_name' => $studentData['father_name'],
                        'class_id' => $class->id,
                        'gender' => $studentData['gender'],
                        'date_of_birth' => '2015-05-15',
                        'admission_date' => now()->format('Y-m-d'),
                        'guardian_name' => $studentData['father_name'],
                        'guardian_relation' => 'Father',
                        'guardian_phone' => '03001234567',
                        'address' => 'Sample Address for ' . $studentData['name'],
                        'status' => 'active',
                    ]
                );
                $rollCounter++;
            }
        }
    }
}
