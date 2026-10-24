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
        Schema::create('player_game_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_game_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedInteger('visit_number');

            $table->timestamp('opened_at');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['player_game_id', 'visit_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_game_visits');
    }
};
