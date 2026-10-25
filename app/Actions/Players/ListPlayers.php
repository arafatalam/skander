<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPlayers
{
    public function __construct(private readonly QueryPlayerBalances $balances) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{id: int, name: string, phone: string|null, email: string|null, is_active: bool, balance: int, show_url: string}>
     */
    public function get(array $filters): LengthAwarePaginator
    {
        $direction = $filters['owing'] ?? 'all';
        $search = $filters['search'] ?? '';
        $query = in_array($direction, ['collect', 'pay'], true)
            ? $this->balances->owing($direction)
            : $this->balances->query()->orderBy('name')->orderBy('id');
        $players = $query->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        })->paginate(20)->appends($filters);

        return $players->through(fn (Player $player) => [
            ...$player->only(['id', 'name', 'phone', 'email', 'is_active']),
            'balance' => (int) $player->getAttribute('book_balance'),
            'show_url' => route('players.show', ['player' => $player->id, ...$filters, 'page' => $players->currentPage()]),
        ]);
    }
}
