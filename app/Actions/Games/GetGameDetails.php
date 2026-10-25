<?php

namespace App\Actions\Games;

use App\Actions\Players\CalculatePlayerBalance;
use App\Models\GameSession;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;

class GetGameDetails
{
    public function __construct(
        private readonly CalculatePlayerBalance $calculatePlayerBalance,
        private readonly GetGameTotals $totals,
        private readonly GetPlayerGameActivity $activity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(GameSession $gameSession): array
    {
        $gameSession->load(
            'playerGames.player',
            'playerGames.gameSession',
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
            'totals' => $this->totals->get($gameSession),
            'players' => $gameSession->playerGames->map(
                fn (PlayerGame $playerGame) => $this->playerDetails($playerGame),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerDetails(PlayerGame $playerGame): array
    {
        return [
            'id' => $playerGame->id,
            'name' => $playerGame->player->name,
            ...$this->activity->get($playerGame),
            'daily_balance' => $this->calculatePlayerBalance->calculate($playerGame->player, $playerGame->gameSession->game_date->toDateString()),
            'visits' => $playerGame->visits->map(
                fn (PlayerGameVisit $visit) => $this->visitDetails($visit),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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
            'cashout' => $visit->cashout === null ? null : $visit->cashout->only([
                'ending_chips',
                'cash_paid',
                'creation_method',
            ]) + ['edit_url' => route('cashouts.edit', $visit->cashout)],
        ];
    }
}
