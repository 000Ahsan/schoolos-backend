<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyDataSeeder extends Seeder
{
    /**
     * Path to the legacy SQL dump.
     */
    protected $sqlPath = '../pybappsc_smartdb.sql';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!file_exists($this->sqlPath)) {
            $this->command->error("Legacy SQL file not found at: {$this->sqlPath}");
            return;
        }

        $this->command->info("Starting legacy student migration...");

        // Ensure we have at least one class to link to
        $defaultClass = Classes::first();
        if (!$defaultClass) {
            $this->command->error("No classes found in the database. Please create at least one class before seeding students.");
            return;
        }

        $userMap = [];

        // Pass 1: Collect User info ONLY (no classes)
        $this->command->info("Pass 1: Collecting Legacy Logins...");
        $handle = fopen($this->sqlPath, "r");
        $currentTable = null;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;

                if (preg_match("/INSERT INTO `([^`]+)`/i", $line, $matches)) {
                    $currentTable = $matches[1];
                    continue;
                }

                if ($line[0] === '(' && $currentTable === 'user_info') {
                    $this->collectUserLogins($line, $userMap);
                }
            }
            fclose($handle);
        }

        // Pass 2: Migrate Students
        $this->command->info("Pass 2: Migrating Students...");
        $handle = fopen($this->sqlPath, "r");
        $currentTable = null;
        $count = 0;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;

                if (preg_match("/INSERT INTO `([^`]+)`/i", $line, $matches)) {
                    $currentTable = $matches[1];
                    continue;
                }

                if ($line[0] === '(' && $currentTable === 'student_info') {
                    $this->migrateStudentRow($line, $defaultClass->id, $userMap);
                    $count++;
                }
            }
            fclose($handle);
        }

        $this->command->info("Migration completed. Processed {$count} students.");
    }

    protected function collectUserLogins($line, &$userMap)
    {
        $data = $this->parseRow($line);
        if (!$data) return;

        // data: [0:id, 1:username, 2:password, 3:user_type, ...]
        $userMap[$data[0]] = [
            'username' => $data[1],
            'password' => $data[2]
        ];
    }

    protected function migrateStudentRow($line, $fallbackClassId, $userMap)
    {
        $data = $this->parseRow($line);
        if (!$data) return;

        $userId = $data[1];
        
        // Gather info from user_info map
        $userInfo = $userMap[$userId] ?? ['username' => null, 'password' => null];

        Student::updateOrCreate(
            ['roll_no' => $data[2] ?? 'GR-' . $data[0]],
            [
                'name' => $data[3],
                'father_name' => $data[4],
                'class_id' => $fallbackClassId, // Target only one class as requested
                'date_of_birth' => ($data[7] && $data[7] !== '0000-00-00') ? $data[7] : '2015-01-01',
                'gender' => strtolower($data[16] ?? 'male') === 'female' ? 'female' : 'male',
                'admission_date' => ($data[12] && $data[12] !== '0000-00-00') ? $data[12] : now()->toDateString(),
                'address' => $data[6],
                'guardian_name' => $data[4],
                'guardian_phone' => $this->formatPhone($data[10] ?: $data[9]),
                'guardian_relation' => 'Father',
                'status' => (int)$data[17] === 1 ? 'active' : 'left',
            ]
        );
    }

    private function parseRow($line)
    {
        $line = trim($line, " ,;");
        $line = trim($line, "()");
        
        $data = str_getcsv($line, ",", "'");
        return array_map(function($val) { 
            $val = ($val !== null) ? trim($val, " '") : null;
            return ($val === 'NULL' || $val === 'null') ? null : $val;
        }, $data);
    }

    private function formatPhone($phone)
    {
        if (!$phone || trim($phone) === '') return '+920000000000';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '03')) {
            return '+92' . substr($phone, 1);
        }
        if (strlen($phone) === 10 && str_starts_with($phone, '3')) {
            return '+92' . $phone;
        }
        if (str_starts_with($phone, '92')) {
            return '+' . $phone;
        }
        
        return '+92' . str_pad($phone, 10, '0', STR_PAD_LEFT);
    }
}
