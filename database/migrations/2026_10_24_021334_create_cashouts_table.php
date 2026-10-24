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
        Schema::create('cashouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_game_visit_id')
                ->unique()
                ->constrained('player_game_visits')
                ->restrictOnDelete();

            $table->unsignedBigInteger('ending_chips');
            $table->unsignedBigInteger('cash_paid');

            $table->enum('creation_method', ['manual', 'automatic']);

            $table->timestamp('cashed_out_at');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashouts');
    }
};
