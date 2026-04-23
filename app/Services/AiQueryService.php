<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiQueryService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.groq.key') ?? env('GROQ_API_KEY');
    }

    /**
     * Handle the natural language question.
     */
    public function handle($question)
    {
        try {
            Log::info("AI Query for Tenant: " . tenant('id') . " - Question: {$question}");
            
            // 1. Hybrid Intent System: Check for predefined common queries
            $predefined = $this->checkPredefinedIntents($question);
            if ($predefined) {
                return $this->executeQueryAndFormat($predefined['sql'], $predefined['format']);
            }

            // 2. Call Groq API to generate SQL
            $sql = $this->generateSql($question);

            // 3. Validate SQL
            $this->validateSql($sql);

            // 4. Execute and Format Response
            return $this->executeQueryAndFormat($sql);

        } catch (Exception $e) {
            Log::error("AI Query Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'type' => 'ai_error'
            ];
        }
    }

    /**
     * Generate SQL from natural language using Groq.
     */
    protected function generateSql($question)
    {
        $prompt = "You are a professional SQL generator for a School Management System.
Database Schema Information (MySQL):
- students (id, name, admission_no, class_id, gender, father_name, status)
- classes (id, name, section)
- academic_years (id, name, is_active)
- fee_invoices (id, invoice_no, student_id, academic_year_id, month, year, net_amount, amount_paid, balance, status, due_date)
- fee_payments (id, invoice_id, student_id, total_amount, payment_date, payment_method, receipt_no)
- fee_payment_allocations (id, payment_id, invoice_id, allocated_amount)
- school_settings (school_name, currency, organization_type)

Rules:
1. ONLY generate SELECT queries.
2. NEVER generate UPDATE, DELETE, INSERT, DROP, or ALTER queries.
3. Use correct table and column names from the schema above.
4. Use MySQL syntax.
5. Join tables when necessary (e.g., students and classes).
6. Return ONLY the raw SQL query string, no explanation, no markdown backticks.

User Question: \"{$question}\"";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl, [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that generates SQL queries.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.1,
            'max_tokens' => 500,
        ]);

        if ($response->failed()) {
            throw new Exception("Groq API request failed: " . $response->body());
        }

        $data = $response->json();
        $sql = trim($data['choices'][0]['message']['content'] ?? '');

        // Remove potential markdown backticks if AI ignored instructions
        $sql = str_replace(['```sql', '```'], '', $sql);
        $sql = trim($sql);

        return $sql;
    }

    /**
     * Validate the generated SQL query.
     */
    protected function validateSql($sql)
    {
        $forbidden = ['insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'create', 'rename', 'grant', 'revoke'];

        foreach ($forbidden as $word) {
            if (stripos($sql, $word) !== false) {
                throw new Exception("Security Violation: Unauthorized SQL operation detected.");
            }
        }

        if (!str_starts_with(strtolower(trim($sql)), 'select')) {
            throw new Exception("Security Violation: Only SELECT queries are permitted.");
        }

        return true;
    }

    /**
     * Execute SQL and format the result into readable text.
     */
    protected function executeQueryAndFormat($sql, $formatTemplate = null)
    {
        // Add safety limit
        if (stripos($sql, 'LIMIT') === false && stripos($sql, 'COUNT(') === false && stripos($sql, 'SUM(') === false) {
            $sql .= " LIMIT 100";
        }

        $results = DB::connection('tenant')->select($sql);

        // Handle case where result is empty or all values are null (aggregates on empty sets)
        $isEmpty = empty($results);
        if (!$isEmpty && count($results) === 1) {
            $isAllNull = true;
            foreach ((array)$results[0] as $val) {
                if ($val !== null) {
                    $isAllNull = false;
                    break;
                }
            }
            if ($isAllNull) {
                $isEmpty = true;
            }
        }

        if ($isEmpty) {
            return [
                'status' => 'success',
                'answer' => "I couldn't find any relevant data for this request.",
                'sql' => $sql,
                'data' => []
            ];
        }

        // If we have a predefined format template, use it (Hybrid Mode)
        if ($formatTemplate) {
            $answer = $formatTemplate;
            foreach ((array)$results[0] as $key => $value) {
                $displayValue = $value ?? 0;
                if (is_numeric($displayValue) && (str_contains($key, 'total') || str_contains($key, 'amount'))) {
                    $displayValue = number_format($displayValue, 2);
                }
                $answer = str_replace("{{$key}}", $displayValue, $answer);
            }
            return [
                'status' => 'success',
                'answer' => $answer,
                'sql' => $sql,
                'data' => $results
            ];
        }

        // Call Groq again to summarize the results into human-readable text
        return $this->summarizeResults($results, $sql);
    }

    /**
     * Summarize SQL results using Groq.
     */
    protected function summarizeResults($results, $sql)
    {
        $resultsJson = json_encode(array_slice($results, 0, 10)); // Top 10 for context

        $prompt = "You are a professional School Management System reporter. 
Summarize the following database results for an administrator.

SQL Query: {$sql}
Data (JSON): {$resultsJson}

Rules:
1. Provide a direct, professional answer.
2. If the data shows currency amounts, format them nicely with symbols if possible (default to school currency if seen in schema).
3. If no specific records are found or values are 0, state that clearly.
4. Keep it concise.

Answer:";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($this->apiUrl, [
            'model' => 'llama-3.1-8b-instant', // Faster model for summary
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.1,
        ]);

        $summary = $response->json()['choices'][0]['message']['content'] ?? "I found the data you requested but encountered an issue formatting the summary. Please see the technical details below.";

        return [
            'status' => 'success',
            'answer' => trim($summary),
            'sql' => $sql,
            'data' => $results
        ];
    }

    /**
     * Check for predefined intents to save API calls.
     */
    protected function checkPredefinedIntents($question)
    {
        $q = strtolower($question);

        // Better keyword matching for "collection"
        if (str_contains($q, 'today') && (str_contains($q, 'collect') || str_contains($q, 'income') || str_contains($q, 'received'))) {
            return [
                'sql' => "SELECT SUM(total_amount) as total FROM fee_payments WHERE DATE(payment_date) = CURDATE()",
                'format' => "The total fee collection for today is {total}."
            ];
        }

        if (str_contains($q, 'students') && (str_contains($q, 'how many') || str_contains($q, 'total') || str_contains($q, 'count'))) {
            return [
                'sql' => "SELECT COUNT(*) as total FROM students WHERE status = 'active'",
                'format' => "There are currently {total} active students in the system."
            ];
        }

        if (str_contains($q, 'unpaid') || str_contains($q, 'defaulters') || (str_contains($q, 'pending') && str_contains($q, 'fee'))) {
            return [
                'sql' => "SELECT COUNT(*) as total FROM fee_invoices WHERE status IN ('pending', 'partial', 'overdue')",
                'format' => "There are currently {total} students with outstanding fees."
            ];
        }

        return null;
    }
}
