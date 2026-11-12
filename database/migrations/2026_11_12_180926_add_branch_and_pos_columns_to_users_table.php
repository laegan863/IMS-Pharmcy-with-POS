<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable until the user admin form replaces public registration (Phase 1, task 4).
            $table->string('username', 50)->nullable()->unique()->after('name');
            // A user without a branch sees no branch-scoped records.
            $table->foreignId('branch_id')->nullable()->after('username')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('supervisor_pin')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'is_active', 'supervisor_pin']);
        });
    }
};
