<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            // Where it happened, for BIR's who/when/where audit trail.
            $table->foreignId('branch_id')->nullable()->index();
            $table->foreignId('terminal_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }
};
