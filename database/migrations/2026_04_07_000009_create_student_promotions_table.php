<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('from_class_id')->constrained('classes');
            $table->foreignId('to_class_id')->nullable()->constrained('classes');
            $table->foreignId('promoted_by'); // References users
            $table->enum('promotion_type', ['promoted', 'repeated', 'graduated', 'left']);
            $table->text('remarks')->nullable();
            $table->timestamp('promoted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
