<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Update requirements
            $table->date('date_of_birth')->nullable(false)->change();
            $table->enum('gender', ['male', 'female', 'other'])->nullable(false)->change();

            // Rename parent fields to guardian
            $table->renameColumn('parent_name', 'guardian_name');
            $table->renameColumn('parent_phone', 'guardian_phone');
            $table->renameColumn('parent_cnic', 'guardian_cnic');

            // Remove WhatsApp field
            $table->dropColumn('parent_whatsapp');

            // Add relation field
            $table->string('guardian_relation', 50)->nullable()->after('guardian_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->change();

            $table->renameColumn('guardian_name', 'parent_name');
            $table->renameColumn('guardian_phone', 'parent_phone');
            $table->renameColumn('guardian_cnic', 'parent_cnic');

            $table->string('parent_whatsapp', 20)->nullable()->after('parent_phone');

            $table->dropColumn('guardian_relation');
        });
    }
};
