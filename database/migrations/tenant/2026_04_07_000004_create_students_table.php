<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('roll_no', 30)->unique();
            $table->string('name', 100);
            $table->string('father_name', 100);
            $table->foreignId('class_id')->constrained('classes');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('admission_date');
            $table->string('b_form_no', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('parent_name', 100)->nullable();
            $table->string('parent_phone', 20);
            $table->string('parent_whatsapp', 20)->nullable();
            $table->string('parent_cnic', 15)->nullable();
            $table->string('emergency_contact', 20)->nullable();
            $table->enum('status', ['active', 'left', 'graduated', 'suspended'])->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
