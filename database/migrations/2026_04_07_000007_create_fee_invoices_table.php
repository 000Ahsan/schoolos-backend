<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->decimal('current_charges', 10, 2);
            $table->decimal('arrears', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->json('discount_breakdown')->nullable();
            $table->decimal('fine', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->decimal('balance', 10, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->timestamps();

            $table->index(['student_id', 'month', 'year'], 'idx_student_month');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_invoices');
    }
};
