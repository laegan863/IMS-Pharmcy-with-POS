<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles are managed on the Roles page. The Owner role is a locked system
 * role that always has every permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label', 100)->nullable()->after('name');
            $table->string('description')->nullable()->after('label');
            $table->boolean('is_system')->default(false)->after('description');
            $table->boolean('requires_two_factor')->default(false)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'description', 'is_system', 'requires_two_factor']);
        });
    }
};
