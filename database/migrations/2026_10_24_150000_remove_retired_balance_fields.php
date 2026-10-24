<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compatibility cleanup for installations that ran the retired migrations.
        foreach ([
            'player_games' => ['opening_balance'],
            'payments' => ['applies_before_games'],
            'payment_corrections' => ['previous_applies_before_games', 'new_applies_before_games'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                }
            }
        }
    }

    public function down(): void
    {
        // Retired snapshots cannot be reconstructed by rolling back this cleanup.
    }
};
