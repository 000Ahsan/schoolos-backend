<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('section', 10)->nullable();
            $table->tinyInteger('numeric_order');
            $table->smallInteger('capacity')->default(40);
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->unique(['name', 'section'], 'unique_class_section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
