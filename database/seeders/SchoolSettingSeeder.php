<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

class SchoolSettingSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'school_name' => 'Future Leaders International School',
                'address' => '123 Education Street, Knowledge City',
                'phone' => '+923000000000',
                'email' => 'info@futureleaders.edu',
                'currency' => 'PKR',
                'current_academic_year' => '2025-26',
                'fee_due_day' => 10,
                'late_fine_per_month' => 500.00,
            ]
        );
    }
}
