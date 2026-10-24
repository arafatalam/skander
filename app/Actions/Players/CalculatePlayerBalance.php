<?php

namespace App\Actions\Players;

use App\Models\BuyIn;
use App\Models\Cashout;
use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CalculatePlayerBalance
{
    // Game activity belongs to its session date; payments belong to paid_at.
    // Historical totals are calculated on read, never copied between games.
    public function calculate(Player $player, ?string $throughDate = null): int
    {
        $balance = 0;
        $participants = fn (Builder $query) => $query->where('player_id', $player->id)
            ->when($throughDate !== null, fn (Builder $q) => $q->whereHas(
                'gameSession', fn (Builder $game) => $game->whereDate('game_date', '<=', $throughDate),
            ));

        foreach (BuyIn::query()->where('type', 'credit')->whereHas('playerGame', $participants)->cursor() as $buyIn) {
            $balance = $this->add($balance, -$buyIn->amount);
        }
        foreach (Cashout::query()->whereHas('playerGameVisit.playerGame', $participants)->cursor() as $cashout) {
            $balance = $this->add($balance, $cashout->ending_chips);
            $balance = $this->add($balance, -$cashout->cash_paid);
        }
        $payments = $player->payments()->when($throughDate !== null,
            fn (Builder $query) => $query->whereDate('paid_at', '<=', $throughDate));
        foreach ($payments->cursor() as $payment) {
            $change = $payment->direction === 'house_pays_player' ? -$payment->amount : $payment->amount;
            $balance = $this->add($balance, $change);
        }

        return $balance;
    }

    private function add(int $balance, int $change): int
    {
        if (($change > 0 && $balance > PHP_INT_MAX - $change)
            || ($change < 0 && $balance < PHP_INT_MIN - $change)) {
            throw ValidationException::withMessages([
                'balance' => 'This activity exceeds the supported balance range.',
            ]);
        }

        return $balance + $change;
    }
}
