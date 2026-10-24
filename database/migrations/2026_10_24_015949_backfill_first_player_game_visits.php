<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('player_games')
                ->orderBy('id')
                ->chunkById(100, function ($participants) {
                    foreach ($participants as $participant) {
                        $firstBuyIn = DB::table('buy_ins')
                            ->where('player_game_id', $participant->id)
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->first();

                        $openedAt = $firstBuyIn->created_at
                            ?? $participant->created_at;

                        $visitId = DB::table('player_game_visits')
                            ->insertGetId([
                                'player_game_id' => $participant->id,
                                'visit_number' => 1,
                                'opened_at' => $openedAt,
                                'created_by' => $firstBuyIn->created_by
                                    ?? $participant->created_by,
                                'created_at' => $openedAt,
                                'updated_at' => $openedAt,
                            ]);

                        DB::table('buy_ins')
                            ->where('player_game_id', $participant->id)
                            ->update([
                                'player_game_visit_id' => $visitId,
                            ]);
                    }
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve visit records: later activity may reference them.
    }
};
