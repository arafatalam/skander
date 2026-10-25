<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class QueryPlayerBalances
{
    /** @return Builder<Player> */
    public function query(): Builder
    {
        // Effective events are the source of truth; corrections are not counted twice.
        $events = Player::query()->select('players.*')
            ->selectSub(DB::table('buy_ins')->join('player_games', 'player_games.id', '=', 'buy_ins.player_game_id')
                ->whereColumn('player_games.player_id', 'players.id')->where('buy_ins.type', 'credit')
                ->selectRaw('COALESCE(SUM(buy_ins.amount), 0)'), 'credit_total')
            ->selectSub(DB::table('cashouts')->join('player_game_visits', 'player_game_visits.id', '=', 'cashouts.player_game_visit_id')
                ->join('player_games', 'player_games.id', '=', 'player_game_visits.player_game_id')
                ->whereColumn('player_games.player_id', 'players.id')
                ->selectRaw('COALESCE(SUM(cashouts.ending_chips - cashouts.cash_paid), 0)'), 'settlement_total')
            ->selectSub(DB::table('payments')->whereColumn('payments.player_id', 'players.id')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'house_pays_player' THEN -amount ELSE amount END), 0)"), 'payment_total');

        $balances = DB::query()->fromSub($events, 'events')->select('events.*')
            ->selectRaw('settlement_total - credit_total + payment_total AS book_balance');

        return Player::query()->fromSub($balances, 'players')->select('players.*');
    }

    /** @return Builder<Player> */
    public function owing(string $direction): Builder
    {
        return $this->query()->where('book_balance', $direction === 'collect' ? '<' : '>', 0)
            ->orderBy('book_balance', $direction === 'collect' ? 'asc' : 'desc')
            ->orderBy('name')->orderBy('id');
    }
}
