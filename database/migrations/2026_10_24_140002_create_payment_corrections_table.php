<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->uuid('submission_id')->unique();
            $table->unsignedBigInteger('previous_amount');
            $table->string('previous_direction');
            $table->date('previous_paid_at');
            $table->text('previous_note')->nullable();
            $table->unsignedBigInteger('new_amount');
            $table->string('new_direction');
            $table->date('new_paid_at');
            $table->text('new_note')->nullable();
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();
            $table->unique(['payment_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_corrections');
    }
};
