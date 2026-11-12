<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One counter per document series, per branch, and per terminal for POS
 * documents. App\Services\DocumentNumbers takes the next number inside the
 * posting transaction with a row lock, so numbers have no gaps: a failed
 * posting rolls the counter back with everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // 0 for branch-wide series; the terminal's id for POS series.
            $table->unsignedBigInteger('terminal_id')->default(0);
            $table->string('series', 40);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'terminal_id', 'series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
