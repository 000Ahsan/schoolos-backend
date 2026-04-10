<?php

namespace Database\Seeders;

use App\Models\Classes;
use Illuminate\Database\Seeder;

class ClassesSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['name' => 'Playgroup', 'section' => 'A', 'numeric_order' => 1],
            ['name' => 'Nursery', 'section' => 'A', 'numeric_order' => 2],
            ['name' => 'Prep', 'section' => 'A', 'numeric_order' => 3],
            ['name' => 'Class 1', 'section' => 'A', 'numeric_order' => 4],
            ['name' => 'Class 2', 'section' => 'A', 'numeric_order' => 5],
            ['name' => 'Class 3', 'section' => 'A', 'numeric_order' => 6],
            ['name' => 'Class 4', 'section' => 'A', 'numeric_order' => 7],
            ['name' => 'Class 5', 'section' => 'A', 'numeric_order' => 8],
            ['name' => 'Class 6', 'section' => 'A', 'numeric_order' => 9],
            ['name' => 'Class 7', 'section' => 'A', 'numeric_order' => 10],
            ['name' => 'Class 8', 'section' => 'A', 'numeric_order' => 11],
            ['name' => 'Class 9', 'section' => 'Computer', 'numeric_order' => 12],
            ['name' => 'Class 10', 'section' => 'Computer', 'numeric_order' => 13],
        ];

        foreach ($classes as $class) {
            Classes::updateOrCreate(
                ['name' => $class['name'], 'section' => $class['section']],
                $class
            );
        }
    }
}
