<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePlayerGame
{
    public function create(GameSession $gameSession, Player $player, User $admin): PlayerGame
    {

        Gate::forUser($admin)->authorize('create', PlayerGame::class);

        return DB::transaction(function () use ($gameSession, $player, $admin) {
            $game = GameSession::query()
                ->where('id', $gameSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($game->status !== 'open') {
                throw ValidationException::withMessages([
                    'game_session' => 'Players cannot join a closed game.',
                ]);
            }

            $currentPlayer = Player::query()
                ->where('id', $player->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingRecord = PlayerGame::query()
                ->where('player_id', $currentPlayer->id)
                ->where('game_session_id', $game->id)
                ->first();

            if ($existingRecord !== null) {
                return $existingRecord;
            }

            return PlayerGame::create([
                'player_id' => $currentPlayer->id,
                'game_session_id' => $game->id,
                'opening_balance' => $currentPlayer->balance,
                'created_by' => $admin->id,
            ]);
        });
    }
}
