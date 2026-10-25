<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use Illuminate\Pagination\LengthAwarePaginator;

class ListGameSessions
{
    public function __construct(private readonly GetGameTotals $totals) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: int, game_name: string, status: string, total_tips: int|null, total_cost: int|null, totals: array{buy_ins: int, ending_chips: int, cash_paid: int, income: int|null}, show_url: string}>
     */
    public function get(array $filters): LengthAwarePaginator
    {
        $games = $this->totals->query()
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('game_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('game_date', '<=', $date))
            ->when(in_array($filters['status'] ?? 'all', ['open', 'closed'], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('game_date')->orderByDesc('game_number')
            ->paginate(20)->appends($filters);

        return $games->through(fn (GameSession $game) => [
            'id' => $game->id, 'game_name' => $game->game_name, 'status' => $game->status,
            'total_tips' => $game->total_tips, 'total_cost' => $game->total_cost,
            'totals' => $this->totals->fromTotals($game),
            'show_url' => route('games.show', ['gameSession' => $game->id, ...$filters, 'page' => $games->currentPage()]),
        ]);
    }
}
