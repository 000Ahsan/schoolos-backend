<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->string('current_academic_year', 20);
            $table->tinyInteger('fee_due_day')->default(10);
            $table->decimal('late_fine_per_month', 10, 2)->default(0.00);
            $table->string('whatsapp_node_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
