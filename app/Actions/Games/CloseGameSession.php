<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseGameSession
{
    public function close(GameSession $gameSession, User $admin): GameSession
    {
        Gate::forUser($admin)->authorize('close', $gameSession);

        $adminId = $admin->id;
        if (! $admin->exists || $adminId < 1) {
            throw new AuthorizationException('A saved Admin account is required to close a game.');
        }

        return DB::transaction(function () use ($gameSession, $admin, $adminId) {
            $game = $this->lockGame($gameSession);

            if ($game->status === 'closed') {
                return $game;
            }

            $closedAt = now();

            app(EnsureNonNegativeGameIncome::class)->ensure($game, closing: true);

            foreach (['tips', 'cost'] as $kind) {
                if ($game->{'total_'.$kind} === null) {
                    $game->{'total_'.$kind} = 0;
                    $game->{$kind.'_recorded_by'} = $adminId;
                    $game->{$kind.'_recorded_at'} = $closedAt;
                }
            }

            $this->recordAutomaticCashouts($game, $admin, $closedAt);
            $this->markClosed($game, $admin, $closedAt);

            return $game;
        });
    }

    private function lockGame(GameSession $gameSession): GameSession
    {
        return GameSession::query()
            ->whereKey($gameSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordAutomaticCashouts(
        GameSession $game,
        User $admin,
        CarbonInterface $closedAt,
    ): void {
        $participants = $game->playerGames()
            ->with('latestVisit.cashout')
            ->get();

        foreach ($participants as $participant) {
            $visit = $participant->latestVisit;

            if ($visit === null || $visit->cashout !== null) {
                continue;
            }

            $visit->cashout()->create([
                'ending_chips' => 0,
                'cash_paid' => 0,
                'creation_method' => 'automatic',
                'cashed_out_at' => $closedAt,
                'created_by' => $admin->id,
            ]);
        }
    }

    private function markClosed(
        GameSession $game,
        User $admin,
        CarbonInterface $closedAt,
    ): void {
        $game->update([
            'status' => 'closed',
            'closed_by' => $admin->id,
            'closed_at' => $closedAt,
        ]);
    }
}
