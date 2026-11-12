<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispensing records. A prescription line tracks quantity prescribed,
 * dispensed and the balance, so partial fills are allowed and recorded
 * (RA 10918). Dangerous drug sales get a register entry with the special
 * prescription form serial, patient, S2 prescriber and dispensing pharmacist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('patient_name', 150);
            $table->string('patient_address')->nullable();
            $table->foreignId('prescriber_id')->constrained()->restrictOnDelete();
            $table->date('prescribed_on');
            // Dangerous drugs: the yellow special prescription form's serial number.
            $table->string('special_form_serial', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('prescription_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_prescribed_base');
            $table->unsignedInteger('quantity_dispensed_base')->default(0);
            $table->string('directions')->nullable();
            $table->timestamps();
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->foreignId('prescription_line_id')->nullable()->after('product_unit_id')->constrained()->nullOnDelete();
        });

        Schema::create('dangerous_drug_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->string('special_form_serial', 50);
            $table->string('patient_name', 150);
            $table->string('patient_address')->nullable();
            $table->foreignId('prescriber_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_base');
            // Branch stock of this product right after the dispensing.
            $table->unsignedInteger('balance_after');
            $table->foreignId('pharmacist_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dangerous_drug_entries');

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_line_id');
        });

        Schema::dropIfExists('prescription_lines');
        Schema::dropIfExists('prescriptions');
    }
};
