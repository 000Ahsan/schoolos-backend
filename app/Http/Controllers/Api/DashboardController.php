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
        $totalCollectedToday = (float) FeePayment::whereDate('payment_date', Carbon::today())->sum('total_amount');
        $totalCollectedMonth = (float) FeePayment::whereMonth('payment_date', Carbon::now()->month)
                                    ->whereYear('payment_date', Carbon::now()->year)
                                    ->sum('total_amount');
        
        $totalCharged = (float) FeeInvoice::whereIn('status', ['pending', 'overdue', 'partial'])->sum('net_amount');
        $totalAllocated = (float) DB::table('fee_payment_allocations')
                                ->join('fee_invoices', 'fee_payment_allocations.invoice_id', '=', 'fee_invoices.id')
                                ->whereIn('fee_invoices.status', ['pending', 'overdue', 'partial'])
                                ->sum('allocated_amount');

        return response()->json([
            'total_students' => Student::where('status', 'active')->count(),
            'collected_today' => $totalCollectedToday,
            'collected_month' => $totalCollectedMonth,
            'pending_amount' => $totalCharged - $totalAllocated,
            'defaulter_count' => Student::whereHas('invoices', function($q) { 
                                     $q->whereIn('status', ['pending', 'overdue', 'partial']); 
                                 })->count()
        ]);
    }

    public function recentPayments() {
        return response()->json(
            FeePayment::with(['student'])
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
                    $total += $invoice->payments->sum('total_amount');
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
                'total' => (float) FeePayment::whereDate('payment_date', $date)->sum('total_amount')
            ];
        });

        return response()->json($days);
    }
}
