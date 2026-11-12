<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short pick-lists used by the product form. The catalog is shared by all
 * branches, so these tables have no branch_id.
 */
return new class extends Migration
{
    private const TABLES = ['categories', 'generic_names', 'dosage_forms', 'manufacturers'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
