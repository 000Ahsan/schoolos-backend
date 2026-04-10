<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\StudentDiscountController;
use App\Http\Controllers\Api\FeeStructureController;
use App\Http\Controllers\Api\FeeInvoiceController;
use App\Http\Controllers\Api\FeePaymentController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\DashboardController;

Route::post('/v1/auth/login', [AuthController::class, 'login']);

Route::get('/v1/ping', function() {
    return response()->json(['status' => 'success', 'message' => 'SchoolOS API is working!']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('students', StudentController::class);
    Route::apiResource('classes', ClassController::class);
    
    Route::get('academic-years', [AcademicYearController::class, 'index']);
    Route::post('academic-years', [AcademicYearController::class, 'store']);
    Route::put('academic-years/{id}', [AcademicYearController::class, 'update']);
    Route::delete('academic-years/{id}', [AcademicYearController::class, 'destroy']);
    Route::post('academic-years/{id}/set-current', [AcademicYearController::class, 'setCurrent']);
    Route::post('academic-years/{id}/promote-students', [AcademicYearController::class, 'promoteStudents']);

    Route::get('students/{studentId}/discounts', [StudentDiscountController::class, 'index']);
    Route::post('students/{studentId}/discounts', [StudentDiscountController::class, 'store']);
    Route::put('students/{studentId}/discounts/{discountId}', [StudentDiscountController::class, 'update']);
    Route::delete('students/{studentId}/discounts/{discountId}', [StudentDiscountController::class, 'destroy']);

    Route::get('fee/structures', [FeeStructureController::class, 'index']);
    Route::post('fee/structures', [FeeStructureController::class, 'store']);
    Route::put('fee/structures/{id}', [FeeStructureController::class, 'update']);
    
    Route::post('fee/invoices/generate', [FeeInvoiceController::class, 'generate']);
    Route::get('fee/invoices', [FeeInvoiceController::class, 'index']);
    Route::get('fee/invoices/{id}', [FeeInvoiceController::class, 'show']);
    Route::get('fee/defaulters', function() {
        return response()->json(\App\Models\Student::whereHas('invoices', function($q) { $q->where('balance', '>', 0); })->get());
    });
    
    Route::post('fee/payments', [FeePaymentController::class, 'store']);

    Route::post('whatsapp/reminder/{studentId}', [WhatsAppController::class, 'reminder']);
    Route::post('whatsapp/reminder/bulk', [WhatsAppController::class, 'bulkReminder']);
    Route::get('whatsapp/logs', [WhatsAppController::class, 'logs']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/recent-payments', [DashboardController::class, 'recentPayments']);
});
