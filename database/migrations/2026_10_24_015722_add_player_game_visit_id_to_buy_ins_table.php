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
        Schema::table('buy_ins', function (Blueprint $table) {
            $table->foreignId('player_game_visit_id')
                ->nullable()
                ->constrained('player_game_visits')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buy_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_game_visit_id');
        });
    }
};
