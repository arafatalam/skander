<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tips', 'cost'] as $kind) {
            DB::table('game_sessions')->where('status', 'closed')->whereNull('total_'.$kind)->update([
                'total_'.$kind => 0,
                $kind.'_recorded_by' => DB::raw('COALESCE(closed_by, created_by)'),
                $kind.'_recorded_at' => DB::raw('COALESCE(closed_at, created_at)'),
            ]);
        }
    }

    public function down(): void
    {
        // Keep recorded zero values and their correction history on rollback.
    }
};
