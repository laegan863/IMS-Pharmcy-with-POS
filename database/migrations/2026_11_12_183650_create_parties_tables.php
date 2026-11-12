<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers, customers and prescribers, shared by all branches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->index();
            $table->string('tin', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('fda_license_number', 50)->nullable();
            $table->date('fda_license_expires_on')->nullable();
            // Days until payment is due; 0 for cash on delivery.
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->date('birthdate')->nullable();
            // Encrypted at rest (Data Privacy Act). The hashes allow lookup by ID number.
            $table->text('senior_citizen_id')->nullable();
            $table->string('senior_citizen_id_hash', 64)->nullable()->index();
            $table->text('pwd_id')->nullable();
            $table->string('pwd_id_hash', 64)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prescribers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('prc_license_number', 30)->nullable()->unique();
            $table->string('ptr_number', 30)->nullable();
            // Required to prescribe dangerous drugs.
            $table->string('s2_license_number', 30)->nullable();
            $table->string('specialty', 100)->nullable();
            $table->string('clinic_address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescribers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
    }
};
