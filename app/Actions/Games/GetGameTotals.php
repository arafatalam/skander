<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GetGameTotals
{
    /** @return Builder<GameSession> */
    public function query(): Builder
    {
        return GameSession::query()->select('game_sessions.*')
            ->selectSub(DB::table('buy_ins')->join('player_games', 'player_games.id', '=', 'buy_ins.player_game_id')
                ->whereColumn('player_games.game_session_id', 'game_sessions.id')
                ->selectRaw('COALESCE(SUM(buy_ins.amount), 0)'), 'buy_in_total')
            ->selectSub($this->cashoutSum('ending_chips'), 'cashout_total')
            ->selectSub($this->cashoutSum('cash_paid'), 'cash_paid_total');
    }

    private function cashoutSum(string $field): \Illuminate\Database\Query\Builder
    {
        return DB::table('cashouts')
            ->join('player_game_visits', 'player_game_visits.id', '=', 'cashouts.player_game_visit_id')
            ->join('player_games', 'player_games.id', '=', 'player_game_visits.player_game_id')
            ->whereColumn('player_games.game_session_id', 'game_sessions.id')
            ->selectRaw($field === 'ending_chips'
                ? 'COALESCE(SUM(cashouts.ending_chips), 0)'
                : 'COALESCE(SUM(cashouts.cash_paid), 0)');
    }

    /** @return array{buy_ins: int, ending_chips: int, cash_paid: int, income: int|null} */
    public function get(GameSession $game): array
    {
        $totals = $this->query()->findOrFail($game->id);

        return $this->fromTotals($totals);
    }

    /** @return array{buy_ins: int, ending_chips: int, cash_paid: int, income: int|null} */
    public function fromTotals(GameSession $game): array
    {
        return [
            'buy_ins' => (int) $game->getAttribute('buy_in_total'),
            'ending_chips' => (int) $game->getAttribute('cashout_total'),
            'cash_paid' => (int) $game->getAttribute('cash_paid_total'),
            'income' => $game->status === 'closed' ? $this->income($game) : null,
        ];
    }

    public function income(GameSession $game): int
    {
        return (int) $game->getAttribute('buy_in_total') - (int) $game->getAttribute('cashout_total')
            - ($game->total_tips ?? 0) - ($game->total_cost ?? 0);
    }
}
