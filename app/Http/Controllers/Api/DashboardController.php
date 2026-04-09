<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\FeePayment;
use Carbon\Carbon;

class DashboardController extends Controller {
    public function stats() {
        return response()->json([
            'total_students' => Student::where('status', 'active')->count(),
            'collected_today' => FeePayment::whereDate('payment_date', Carbon::today())->sum('amount_paid'),
            'collected_month' => FeePayment::whereMonth('payment_date', Carbon::now()->month)->sum('amount_paid'),
            'defaulters_count' => Student::whereHas('invoices', function($q) { $q->where('balance', '>', 0); })->count()
        ]);
    }
    public function recentPayments() {
        return response()->json(FeePayment::with(['invoice.student', 'receiver'])->orderByDesc('payment_date')->take(10)->get());
    }
}
