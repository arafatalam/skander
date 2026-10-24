<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;

class GetGameDetails
{
    public function __construct(
        private readonly CalculateGameBalance $calculateGameBalance,
    ) {}

    public function get(GameSession $gameSession): array
    {
        $gameSession->load(
            'playerGames.player',
            'playerGames.buyIns',
            'playerGames.visits.buyIns',
            'playerGames.visits.cashout',
        );

        return [
            'id' => $gameSession->id,
            'game_name' => $gameSession->game_name,
            'status' => $gameSession->status,
            'total_tips' => $gameSession->total_tips,
            'total_cost' => $gameSession->total_cost,
            'totals' => $this->gameTotals($gameSession),
            'players' => $gameSession->playerGames->map(
                fn (PlayerGame $playerGame) => $this->playerDetails($playerGame),
            ),
        ];
    }

    private function gameTotals(GameSession $gameSession): array
    {
        $buyIns = $gameSession->playerGames
            ->flatMap(fn (PlayerGame $playerGame) => $playerGame->buyIns);

        $cashouts = $gameSession->playerGames
            ->flatMap(fn (PlayerGame $playerGame) => $playerGame->visits)
            ->map(fn (PlayerGameVisit $visit) => $visit->cashout)
            ->filter();

        return [
            'buy_ins' => (int) $buyIns->sum('amount'),
            'ending_chips' => (int) $cashouts->sum('ending_chips'),
            'cash_paid' => (int) $cashouts->sum('cash_paid'),
        ];
    }

    private function playerDetails(PlayerGame $playerGame): array
    {
        return [
            'id' => $playerGame->id,
            'name' => $playerGame->player->name,
            'opening_balance' => $playerGame->opening_balance,
            'balance' => $this->calculateGameBalance->calculate($playerGame),
            'visits' => $playerGame->visits->map(
                fn (PlayerGameVisit $visit) => $this->visitDetails($visit),
            ),
        ];
    }

    private function visitDetails(PlayerGameVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'visit_number' => $visit->visit_number,
            'buy_ins' => $visit->buyIns->map(fn ($buyIn) => [
                'id' => $buyIn->id,
                'type' => $buyIn->type,
                'amount' => $buyIn->amount,
            ]),
            'cashout' => $visit->cashout?->only([
                'ending_chips',
                'cash_paid',
                'creation_method',
            ]),
        ];
    }
}
