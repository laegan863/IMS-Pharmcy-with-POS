<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The stock ledger. On-hand stock is the sum of stock_movements, kept per
 * batch and branch; stock_balances holds that sum so reads are fast and so
 * sales can lock one row per batch. Only App\Services\Stock\StockService
 * writes to these tables.
 *
 * Quantities are whole numbers in the product's base unit. Costs are per base
 * unit with four decimals, because a box price divided by its size rarely
 * comes out to whole centavos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Moving weighted average cost per base unit, updated on every receipt.
            $table->decimal('average_cost', 14, 4)->default(0)->after('max_level');
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('lot_number', 50);
            $table->date('expires_on')->index();
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            // active, quarantined, expired, disposed
            $table->string('status', 20)->default('active')->index();
            $table->string('status_reason')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'lot_number', 'expires_on']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'batch_id']);
            $table->index(['branch_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Signed, in base units: positive adds stock, negative removes it.
            $table->integer('quantity');
            $table->string('type', 30)->index();
            $table->decimal('unit_cost', 14, 4)->default(0);
            // The document that caused the movement (goods receipt, sale, count...).
            $table->nullableMorphs('source');
            $table->string('reference', 50)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['branch_id', 'batch_id']);
            $table->index(['branch_id', 'product_id', 'created_at']);
        });

        $this->guardBalances();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('batches');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });
    }

    /**
     * A balance can never go below zero, enforced by the database as well as
     * by StockService. MySQL and MariaDB get a CHECK constraint (on top of the
     * unsigned column); SQLite, which ignores unsigned, gets triggers.
     */
    private function guardBalances(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_quantity_not_negative CHECK (quantity >= 0)');

            return;
        }

        if ($driver === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                $name = 'stock_balances_not_negative_'.strtolower($event);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON stock_balances
                    WHEN NEW.quantity < 0
                    BEGIN SELECT RAISE(ABORT, 'stock balance cannot be negative'); END;");
            }
        }
    }
};
