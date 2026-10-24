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
            $game = $this->lockGame($gameSession);

            $this->ensureGameIsOpen($game);

            $currentPlayer = $this->lockPlayer($player);

            $existingRecord = $this->findExistingParticipant($currentPlayer, $game);

            if ($existingRecord !== null) {
                return $existingRecord;
            }

            $this->ensurePlayerHasNoOtherOpenGame($currentPlayer, $game);

            return $this->saveParticipant($currentPlayer, $game, $admin);
        });
    }

    private function lockGame(GameSession $gameSession): GameSession
    {
        return GameSession::query()
            ->whereKey($gameSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockPlayer(Player $player): Player
    {
        return Player::query()
            ->whereKey($player->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function saveParticipant(Player $player, GameSession $game, User $admin): PlayerGame
    {
        return PlayerGame::create([
            'player_id' => $player->id,
            'game_session_id' => $game->id,
            'opening_balance' => $player->balance,
            'created_by' => $admin->id,
        ]);
    }

    private function ensurePlayerHasNoOtherOpenGame(Player $player, GameSession $game): void
    {
        $hasAnotherOpenGame = PlayerGame::query()
            ->where('player_id', $player->id)
            ->where('game_session_id', '!=', $game->id)
            ->whereHas('gameSession', function ($query) {
                $query->where('status', 'open');
            })
            ->exists();

        if ($hasAnotherOpenGame) {
            throw ValidationException::withMessages([
                'player_id' => 'This player already belongs to another open game.',
            ]);
        }
    }

    private function ensureGameIsOpen(GameSession $game): void
    {
        if ($game->status !== 'open') {
            throw ValidationException::withMessages([
                'game_session_id' => 'Players cannot join a closed game.',
            ]);
        }
    }

    private function findExistingParticipant(Player $player, GameSession $game): ?PlayerGame
    {
        return PlayerGame::query()
            ->where('player_id', $player->id)
            ->where('game_session_id', $game->id)
            ->first();
    }
}
