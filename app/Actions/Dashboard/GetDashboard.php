<?php

namespace App\Actions\Dashboard;

use App\Actions\Games\GetGameTotals;
use App\Actions\Players\QueryPlayerBalances;
use App\Models\GameSession;
use App\Models\Player;

class GetDashboard
{
    public function __construct(
        private readonly QueryPlayerBalances $balances,
        private readonly GetGameTotals $totals,
    ) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $today = now();
        $from = $today->startOfMonth()->toDateString();
        $to = $today->endOfMonth()->toDateString();
        $monthGames = $this->totals->query()->where('status', 'closed')
            ->whereDate('game_date', '>=', $from)->whereDate('game_date', '<=', $to)->get();
        $outstanding = [];
        foreach (['collect', 'pay'] as $direction) {
            $query = $this->balances->owing($direction);
            $outstanding[$direction] = [
                'total' => abs((int) (clone $query)->reorder()->sum('book_balance')),
                'count' => (clone $query)->count(),
                'url' => route('players', ['owing' => $direction]),
                'players' => $query->limit(5)->get()->map(fn (Player $player) => [
                    'id' => $player->id, 'name' => $player->name,
                    'amount' => abs((int) $player->getAttribute('book_balance')),
                    'url' => route('players.show', ['player' => $player->id, 'from' => 'dashboard']),
                ]),
            ];
        }

        return [
            'date' => $today->format('l, j F Y'),
            'month' => $today->format('F'),
            'outstanding' => $outstanding,
            'income' => [
                'total' => $monthGames->sum(fn (GameSession $game) => $this->totals->income($game)),
                'count' => $monthGames->count(),
                'url' => route('games.index', ['from_date' => $from, 'to_date' => $to, 'status' => 'closed']),
            ],
            'open_games' => $this->totals->query()->where('status', 'open')
                ->withCount(['playerGames as still_playing' => fn ($query) => $query->whereHas('latestVisit', fn ($visit) => $visit->whereDoesntHave('cashout'))])
                ->orderByDesc('game_date')->orderByDesc('game_number')->get()
                ->map(fn (GameSession $game) => [
                    ...$this->game($game), 'still_playing' => (int) $game->getAttribute('still_playing'),
                ]),
            'recent_games' => $this->totals->query()->where('status', 'closed')
                ->orderByDesc('game_date')->orderByDesc('game_number')->limit(5)->get()
                ->map(fn (GameSession $game) => $this->game($game)),
        ];
    }

    /** @return array<string, mixed> */
    private function game(GameSession $game): array
    {
        return [
            'id' => $game->id, 'name' => $game->game_name,
            'url' => route('games.show', $game), 'totals' => $this->totals->fromTotals($game),
        ];
    }
}
