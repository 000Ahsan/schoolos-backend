<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $years = [
            [
                'label' => '2023-24',
                'start_date' => '2023-04-01',
                'end_date' => '2024-03-31',
                'is_current' => false,
                'is_locked' => true,
            ],
            [
                'label' => '2024-25',
                'start_date' => '2024-04-01',
                'end_date' => '2025-03-31',
                'is_current' => false,
                'is_locked' => false,
            ],
            [
                'label' => '2025-26',
                'start_date' => '2025-04-01',
                'end_date' => '2026-03-31',
                'is_current' => true,
                'is_locked' => false,
            ],
        ];

        foreach ($years as $year) {
            AcademicYear::updateOrCreate(['label' => $year['label']], $year);
        }
    }
}
