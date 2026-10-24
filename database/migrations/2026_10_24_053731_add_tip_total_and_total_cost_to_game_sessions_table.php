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
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('total_tips')->nullable();
            $table->unsignedBigInteger('total_cost')->nullable();

            $table->foreignId('tips_recorded_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('tips_recorded_at')->nullable();

            $table->foreignId('cost_recorded_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('cost_recorded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tips_recorded_by');
            $table->dropConstrainedForeignId('cost_recorded_by');

            $table->dropColumn([
                'total_tips',
                'total_cost',
                'tips_recorded_at',
                'cost_recorded_at',
            ]);
        });
    }
};
