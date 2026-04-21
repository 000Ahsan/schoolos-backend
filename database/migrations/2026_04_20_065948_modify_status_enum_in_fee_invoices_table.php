<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE fee_invoices CHANGE status status ENUM('pending', 'partial', 'paid', 'overdue', 'waived', 'carried_forward') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE fee_invoices CHANGE status status ENUM('pending', 'partial', 'paid', 'overdue', 'waived') DEFAULT 'pending'");
    }
};
