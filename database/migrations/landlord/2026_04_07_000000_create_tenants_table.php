<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('subdomain', 100)->unique();
            $table->string('db_host')->default('127.0.0.1');
            $table->smallInteger('db_port')->default(3306);
            $table->string('db_name', 100);
            $table->string('db_username', 100);
            $table->string('db_password');
            $table->enum('deployment', ['cloud', 'onpremise'])->default('cloud');
            $table->enum('plan', ['starter', 'growth', 'pro'])->default('starter');
            $table->boolean('is_active')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
