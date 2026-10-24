<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buy_in_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_in_id')->constrained('buy_ins')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->uuid('submission_id')->unique();
            $table->unsignedBigInteger('previous_amount');
            $table->string('previous_type');
            $table->unsignedBigInteger('new_amount');
            $table->string('new_type');
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();
            $table->unique(['buy_in_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buy_in_corrections');
    }
};
