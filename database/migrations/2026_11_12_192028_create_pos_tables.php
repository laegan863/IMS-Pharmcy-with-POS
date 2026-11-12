<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point of sale. A completed sale is never edited: voids and refunds are new
 * documents (sale_adjustments) that point back at it. Each terminal keeps a
 * running grand total that is never reset, as BIR requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            // Accumulated sales since the terminal was put in service; never reset.
            $table->decimal('grand_total', 16, 2)->default(0)->after('serial_number');
            $table->unsignedInteger('z_counter')->default(0)->after('grand_total');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            // open, closed
            $table->string('status', 10)->default('open')->index();
            $table->decimal('opening_cash', 12, 2);
            $table->timestamp('opened_at');
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->json('denominations')->nullable();
            $table->json('x_reading')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['terminal_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            // in, out
            $table->string('type', 10);
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            // Sent by the POS with each attempt so a retry after a dropped
            // connection returns the same sale instead of posting twice.
            $table->uuid('client_uuid')->unique();
            // completed, voided, refunded (partly or fully)
            $table->string('status', 20)->default('completed')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // none, senior, pwd
            $table->string('discount_type', 10)->default('none');
            $table->text('discount_id_number')->nullable();
            $table->string('discount_holder_name')->nullable();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('vatable_sales', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('vat_exempt_sales', 12, 2);
            $table->decimal('zero_rated_sales', 12, 2);
            $table->decimal('statutory_discount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('change_due', 12, 2);
            $table->decimal('cost_total', 14, 4)->default(0);
            // Set when the receipt is first printed; every later print is a reprint.
            $table->timestamp('printed_at')->nullable();
            $table->unsignedSmallInteger('reprint_count')->default(0);
            $table->timestamp('completed_at')->index();
            $table->timestamps();

            $table->index(['terminal_id', 'completed_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_base');
            // VAT-inclusive price of one unit as charged (after any override).
            $table->decimal('unit_price', 12, 2);
            $table->decimal('list_price', 12, 2);
            $table->foreignId('price_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tax_class', 20);
            $table->boolean('statutory_discount_applied')->default(false);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('vatable_amount', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('vat_exempt_amount', 12, 2);
            $table->decimal('zero_rated_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->decimal('cost_total', 14, 4)->default(0);
            $table->unsignedInteger('refunded_base')->default(0);
            $table->timestamps();
        });

        Schema::create('sale_line_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_base');
            $table->decimal('unit_cost', 14, 4);
            $table->unsignedInteger('returned_base')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            // cash, ewallet, card
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sale_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            // void, refund
            $table->string('type', 10)->index();
            $table->string('number', 40)->unique();
            $table->string('reason');
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('sale_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_adjustment_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_base');
            $table->decimal('amount', 12, 2);
        });

        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('label', 100);
            $table->json('cart');
            $table->timestamps();
        });

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            // z only; X-readings live on the shift
            $table->string('type', 1);
            $table->string('number', 40)->unique();
            $table->unsignedInteger('z_counter');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->decimal('grand_total_before', 16, 2);
            $table->decimal('grand_total_after', 16, 2);
            $table->json('data');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['readings', 'held_sales', 'sale_adjustment_lines', 'sale_adjustments', 'payments', 'sale_line_batches', 'sale_lines', 'sales', 'cash_movements', 'shifts'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['grand_total', 'z_counter']);
        });
    }
};
