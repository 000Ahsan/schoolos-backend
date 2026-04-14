<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\FeePayment;
use App\Models\FeeInvoice;
use App\Models\Classes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    public function stats() {
        return response()->json([
            'total_students' => Student::where('status', 'active')->count(),
            'collected_today' => (float) FeePayment::whereDate('payment_date', Carbon::today())->sum('amount_paid'),
            'collected_month' => (float) FeePayment::whereMonth('payment_date', Carbon::now()->month)
                                    ->whereYear('payment_date', Carbon::now()->year)
                                    ->sum('amount_paid'),
            'pending_amount' => (float) FeeInvoice::where('status', '!=', 'paid')->sum('balance'),
            'defaulter_count' => Student::whereHas('invoices', function($q) { 
                                     $q->where('balance', '>', 0); 
                                 })->count()
        ]);
    }

    public function recentPayments() {
        return response()->json(
            FeePayment::with(['invoice.student'])
                ->orderByDesc('created_at')
                ->take(6)
                ->get()
        );
    }

    public function classCollection() {
        $data = Classes::with(['students.invoices.payments' => function($q) {
            $q->whereMonth('payment_date', Carbon::now()->month)
              ->whereYear('payment_date', Carbon::now()->year);
        }])->get()->map(function($class) {
            $total = 0;
            foreach($class->students as $student) {
                foreach($student->invoices as $invoice) {
                    $total += $invoice->payments->sum('amount_paid');
                }
            }
            return [
                'name' => $class->name,
                'total' => $total
            ];
        });

        return response()->json($data);
    }

    public function weeklyCollection() {
        $days = collect(range(6, 0))->map(function($i) {
            $date = Carbon::today()->subDays($i);
            return [
                'date' => $date->format('D'),
                'total' => (float) FeePayment::whereDate('payment_date', $date)->sum('amount_paid')
            ];
        });

        return response()->json($days);
    }
}
