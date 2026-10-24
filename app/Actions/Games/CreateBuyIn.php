<?php

namespace App\Actions\Games;

use App\Models\BuyIn;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBuyIn
{
    public function __construct(
        private readonly CreatePlayerGame $createPlayerGame,
    ) {}

    public function create(
        GameSession $gameSession,
        Player $player,
        User $admin,
        int $cash,
        int $credit,
    ): PlayerGame {
        Gate::forUser($admin)->authorize('create', BuyIn::class);

        return DB::transaction(function () use (
            $gameSession,
            $player,
            $admin,
            $cash,
            $credit,
        ) {
            $game = $this->lockGame($gameSession);

            $this->validateAmounts($cash, $credit);
            $this->ensureTotalMeetsGameLimits($game, $cash + $credit);

            $participant = $this->createPlayerGame->create(
                $game,
                $player,
                $admin,
            );

            $visit = $this->findOrCreateFirstVisit($participant, $admin);
            $this->saveBuyInRecords($participant, $visit, $cash, $credit, $admin);

            $this->subtractCreditFromBalance($participant, $credit);

            return $participant;
        });
    }

    private function validateAmounts(
        int $cash,
        int $credit,
    ): void {
        $amounts = [
            'cash_amount' => $cash,
            'credit_amount' => $credit,
        ];

        foreach ($amounts as $field => $amount) {
            if ($amount < 0 || $amount > 2147483647) {
                throw ValidationException::withMessages([
                    $field => 'Enter a whole-dollar amount between $0 and $2,147,483,647.',
                ]);
            }
        }
    }

    private function ensureTotalMeetsGameLimits(GameSession $game, int $total): void
    {
        if ($total < $game->min_buy_in || $total > $game->max_buy_in) {
            throw ValidationException::withMessages([
                'buy_in_total' => "The combined cash and credit amount must be between \${$game->min_buy_in} and \${$game->max_buy_in}.",
            ]);
        }
    }

    private function subtractCreditFromBalance(
        PlayerGame $participant,
        int $credit,
    ): void {
        if ($credit === 0) {
            return;
        }

        $player = $this->lockParticipantPlayer($participant);
        $this->ensureCreditFitsBalance($player->balance, $credit);
        $this->subtractCredit($player, $credit);
    }

    private function ensureCreditFitsBalance(int $currentBalance, int $credit): void
    {
        $lowestStartingBalance = PHP_INT_MIN + $credit;

        if ($currentBalance < $lowestStartingBalance) {
            throw ValidationException::withMessages([
                'credit_amount' => 'This credit buy-in would exceed the supported balance range.',
            ]);
        }
    }

    private function subtractCredit(Player $player, int $credit): void
    {
        $player->decrement('balance', $credit);
    }

    private function lockGame(GameSession $gameSession): GameSession
    {
        return GameSession::query()
            ->whereKey($gameSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockParticipantPlayer(PlayerGame $participant): Player
    {
        return $participant->player()
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function findOrCreateFirstVisit(PlayerGame $participant, User $admin): PlayerGameVisit
    {
        /** @var PlayerGameVisit $visit */
        return $participant->visits()->firstOrCreate(
            ['visit_number' => 1],
            [
                'opened_at' => now(),
                'created_by' => $admin->id,
            ],
        );
    }

    private function saveBuyInRecords(
        PlayerGame $participant,
        PlayerGameVisit $visit,
        int $cash,
        int $credit,
        User $admin,
    ): void {
        $submissionId = (string) Str::uuid();

        $buyInAmounts = [
            'cash' => $cash,
            'credit' => $credit,
        ];

        foreach ($buyInAmounts as $type => $amount) {
            if ($amount === 0) {
                continue;
            }

            $participant->buyIns()->create([
                'player_game_visit_id' => $visit->id,
                'amount' => $amount,
                'type' => $type,
                'submission_id' => $submissionId,
                'created_by' => $admin->id,
            ]);
        }
    }
}
