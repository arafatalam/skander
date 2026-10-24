<?php

namespace App\Actions\Games;

use App\Models\Cashout;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateCashout
{
    public function create(
        GameSession $gameSession,
        Player $player,
        User $admin,
        int $endingChips,
        int $cashPaid,
        int $expectedVisitId,
    ): Cashout {
        Gate::forUser($admin)->authorize('create', Cashout::class);

        return DB::transaction(function () use (
            $gameSession,
            $player,
            $admin,
            $endingChips,
            $cashPaid,
            $expectedVisitId,
        ) {
            $game = $this->lockGame($gameSession);
            $this->ensureGameIsOpen($game);
            $this->validateAmounts($endingChips, $cashPaid);

            $currentPlayer = $this->lockPlayer($player);
            $participant = $this->lockParticipant($currentPlayer, $game);
            $visit = $this->lockLatestVisit($participant);
            $this->ensureVisitHasBuyIn($visit);
            $this->ensureVisitMatchesSubmission($visit, $expectedVisitId);
            $this->ensureVisitIsOpen($visit);

            $newBalance = $this->calculateNewBalance($currentPlayer->balance, $endingChips, $cashPaid);
            $cashout = $this->saveCashout($visit, $admin, $endingChips, $cashPaid);
            $this->savePlayerBalance($currentPlayer, $newBalance);

            return $cashout;
        });
    }

    private function lockGame(GameSession $gameSession): GameSession
    {
        return GameSession::query()
            ->whereKey($gameSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureGameIsOpen(GameSession $game): void
    {
        if ($game->status !== 'open') {
            throw ValidationException::withMessages([
                'game_session_id' => 'Cashouts cannot be recorded in a closed game.',
            ]);
        }
    }

    private function validateAmounts(
        int $endingChips,
        int $cashPaid,
    ): void {
        $amounts = [
            'ending_chips' => $endingChips,
            'cash_paid' => $cashPaid,
        ];

        foreach ($amounts as $field => $amount) {
            if ($amount < 0 || $amount > 2147483647) {
                throw ValidationException::withMessages([
                    $field => 'Enter a whole-dollar amount between $0 and $2,147,483,647.',
                ]);
            }
        }
    }

    private function lockPlayer(Player $player): Player
    {
        return Player::query()
            ->whereKey($player->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockParticipant(Player $player, GameSession $game): PlayerGame
    {
        $participant = $player->playerGames()
            ->where('game_session_id', $game->id)
            ->lockForUpdate()
            ->first();

        if ($participant === null) {
            throw ValidationException::withMessages([
                'player_id' => 'Record a buy-in for this player in this game before cashing out.',
            ]);
        }

        return $participant;
    }

    private function lockLatestVisit(PlayerGame $participant): PlayerGameVisit
    {
        $visit = $participant->visits()
            ->reorder('visit_number', 'desc')
            ->lockForUpdate()
            ->first();

        if ($visit === null) {
            throw ValidationException::withMessages([
                'player_id' => 'Record a buy-in for this player before cashing out.',
            ]);
        }

        return $visit;
    }

    private function ensureVisitHasBuyIn(PlayerGameVisit $visit): void
    {
        if (! $visit->buyIns()->exists()) {
            throw ValidationException::withMessages([
                'player_id' => 'Record a buy-in for this player before cashing out.',
            ]);
        }
    }

    private function ensureVisitMatchesSubmission(PlayerGameVisit $visit, int $expectedVisitId): void
    {
        if ($visit->id !== $expectedVisitId) {
            throw ValidationException::withMessages([
                'player_id' => 'This player’s visit has changed. Refresh the cashout form and try again.',
            ]);
        }
    }

    private function ensureVisitIsOpen(PlayerGameVisit $visit): void
    {
        if ($visit->cashout()->exists()) {
            throw ValidationException::withMessages([
                'player_id' => 'This player has already cashed out of their latest visit.',
            ]);
        }
    }

    private function calculateNewBalance(int $currentBalance, int $endingChips, int $cashPaid): int
    {
        $balanceChange = $endingChips - $cashPaid;

        if (
            ($balanceChange > 0
                && $currentBalance > PHP_INT_MAX - $balanceChange)
            || ($balanceChange < 0
                && $currentBalance < PHP_INT_MIN - $balanceChange)
        ) {
            throw ValidationException::withMessages([
                'ending_chips' => 'This cashout would exceed the supported balance range.',
            ]);
        }

        return $currentBalance + $balanceChange;
    }

    private function saveCashout(PlayerGameVisit $visit, User $admin, int $endingChips, int $cashPaid): Cashout
    {
        return $visit->cashout()->create([
            'ending_chips' => $endingChips,
            'cash_paid' => $cashPaid,
            'creation_method' => 'manual',
            'cashed_out_at' => now(),
            'created_by' => $admin->id,
        ]);
    }

    private function savePlayerBalance(Player $player, int $newBalance): void
    {
        $player->balance = $newBalance;
        $player->save();
    }
}
