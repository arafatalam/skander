<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_total_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_session_id')
                ->constrained('game_sessions')
                ->restrictOnDelete();

            $table->enum('field', ['total_tips', 'total_cost']);

            $table->unsignedBigInteger('previous_amount');
            $table->unsignedBigInteger('new_amount');

            $table->text('reason');

            $table->foreignId('corrected_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('corrected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_total_corrections');
    }
};
