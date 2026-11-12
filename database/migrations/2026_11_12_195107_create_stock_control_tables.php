<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock control documents. Adjustments and disposals share one table (a
 * disposal is a negative adjustment with a method and a witness). Counts
 * freeze the system quantity when counting starts and post only the
 * variance. Transfers take stock out at dispatch and put it into the same
 * batch at the receiving branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // adjustment, disposal
            $table->string('type', 20)->index();
            $table->string('number', 40)->nullable()->unique();
            // draft, posted
            $table->string('status', 20)->default('draft')->index();
            $table->string('reason_code', 30);
            $table->text('note')->nullable();
            // Disposal only.
            $table->string('disposal_method', 100)->nullable();
            $table->string('witness_name', 150)->nullable();
            $table->decimal('value', 14, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Signed, in base units.
            $table->integer('quantity_change');
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->nullable()->unique();
            // counting, reviewed, posted
            $table->string('status', 20)->default('counting')->index();
            $table->string('scope');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // System quantity when counting started.
            $table->unsignedInteger('system_quantity');
            $table->unsignedInteger('counted_quantity')->nullable();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            // The sending branch; the document is listed in both branches.
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('number', 40)->nullable()->unique();
            // draft, in_transit, received
            $table->string('status', 20)->default('draft')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_base');
        });
    }

    public function down(): void
    {
        foreach (['stock_transfer_lines', 'stock_transfers', 'stock_count_lines', 'stock_counts', 'stock_adjustment_lines', 'stock_adjustments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
