<?php

namespace App\Actions\Games;

use App\Models\BuyIn;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
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
            $game = GameSession::query()
                ->where('id', $gameSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateAmounts($game, $cash, $credit);

            $participant = $this->createPlayerGame->create(
                $game,
                $player,
                $admin,
            );

            $this->saveBuyInRecords($participant, $cash, $credit, $admin);

            $this->subtractCreditFromBalance($participant, $credit);

            return $participant;
        });
    }

    private function validateAmounts(
        GameSession $game,
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

        $total = $cash + $credit;

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

        $player = $participant->player()
            ->lockForUpdate()
            ->firstOrFail();

        $lowestStartingBalance = PHP_INT_MIN + $credit;

        if ($player->balance < $lowestStartingBalance) {
            throw ValidationException::withMessages([
                'credit_amount' => 'This credit buy-in would exceed the supported balance range.',
            ]);
        }

        $player->decrement('balance', $credit);
    }

    private function saveBuyInRecords(PlayerGame $participant, int $cash, int $credit, User $admin): void
    {
        $visit = $participant->visits()->firstOrCreate(
            ['visit_number' => 1],
            [
                'opened_at' => now(),
                'created_by' => $admin->id,
            ],
        );

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
