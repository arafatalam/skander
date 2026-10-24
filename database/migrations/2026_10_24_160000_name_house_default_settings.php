<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('game_settings')->count() > 1) {
            throw new RuntimeException('Resolve duplicate game settings before naming the house defaults.');
        }

        Schema::table('game_settings', function (Blueprint $table) {
            $table->string('key')->default('house_defaults')->unique();
        });

        DB::table('game_settings')->insertOrIgnore([
            'key' => 'house_defaults',
            'default_min_buy_in' => 100,
            'default_max_buy_in' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('game_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
