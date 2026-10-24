<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashout_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashout_id')->constrained('cashouts')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->uuid('submission_id')->unique();
            $table->unsignedBigInteger('previous_ending_chips');
            $table->unsignedBigInteger('previous_cash_paid');
            $table->unsignedBigInteger('new_ending_chips');
            $table->unsignedBigInteger('new_cash_paid');
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();
            $table->unique(['cashout_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashout_corrections');
    }
};
