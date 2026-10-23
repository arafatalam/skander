<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = DB::table('game_settings')
            ->where('id', 1)
            ->first();

        if ($settings === null) {
            throw new RuntimeException(
                'Game settings must exist before adding game buy-in limits.'
            );
        }

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedInteger('min_buy_in')->nullable();
            $table->unsignedInteger('max_buy_in')->nullable();
        });

        DB::table('game_sessions')->update([
            'min_buy_in' => $settings->default_min_buy_in,
            'max_buy_in' => $settings->default_max_buy_in,
        ]);

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedInteger('min_buy_in')->nullable(false)->change();
            $table->unsignedInteger('max_buy_in')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['min_buy_in', 'max_buy_in']);
        });
    }
};
