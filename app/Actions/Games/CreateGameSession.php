<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateGameSession
{
    public function create(string $gameDate, User $admin): GameSession
    {
        $game = DB::transaction(function () use ($gameDate, $admin) {
            // Make simultaneous requests from the Admin wait their turn.
            User::query()
                ->whereKey($admin->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Find the next game number for this date.
            $lastNumber = GameSession::query()
                ->where('game_date', $gameDate)
                ->max('game_number');

            $gameNumber = ($lastNumber ?? 0) + 1;

            // Build a name such as 03_OCT_2026_2.
            $formattedDate = Carbon::parse($gameDate)->format('d_M_Y');

            $gameName = strtoupper($formattedDate).'_'.$gameNumber;

            // Save the game.
            $gameSession = GameSession::create([
                'game_date' => $gameDate,
                'game_number' => $gameNumber,
                'game_name' => $gameName,
                'status' => 'open',
                'created_by' => $admin->getKey(),
            ]);

            return $gameSession;
        });

        return $game;

    }
}
