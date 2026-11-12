<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The product catalog. Stock is always counted in the product's base unit
 * (tablet, ml, piece); other units convert to it with a whole-number factor.
 * The catalog is shared by all branches; stock and documents carry branch_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->foreignId('generic_name_id')->constrained()->restrictOnDelete();
            $table->string('brand_name', 150)->nullable()->index();
            $table->string('strength', 50)->nullable();
            $table->foreignId('dosage_form_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            // rx, otc, dangerous_drug, non_drug
            $table->string('classification', 20)->index();
            $table->string('fda_registration_number', 50)->nullable();
            // vatable, vat_exempt, zero_rated
            $table->string('tax_class', 20);
            $table->date('tax_class_effective_from')->nullable();
            // Senior citizen and PWD 20% applies to medicines, not to non-drug items.
            $table->boolean('statutory_discount_eligible')->default(true);
            $table->string('storage_condition', 20)->default('room');
            // In base units.
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('max_level')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            // How many base units one of this unit holds; 1 for the base unit.
            $table->unsignedInteger('conversion_factor');
            $table->boolean('is_base')->default(false);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            // Selling price for one of this unit, VAT-inclusive per Settings.
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // A barcode identifies the unit scanned (a box barcode adds a box).
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->timestamps();
        });

        Schema::create('product_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['product_unit_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_changes');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
    }
};
