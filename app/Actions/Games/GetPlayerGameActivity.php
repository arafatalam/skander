<?php

namespace App\Actions\Games;

use App\Models\PlayerGame;
use Illuminate\Support\Collection;

class GetPlayerGameActivity
{
    /** @return array<string, mixed> */
    public function get(PlayerGame $playerGame): array
    {
        $cash = (int) $playerGame->buyIns->where('type', 'cash')->sum('amount');
        $credit = (int) $playerGame->buyIns->where('type', 'credit')->sum('amount');
        $cashouts = $playerGame->visits->pluck('cashout')->filter();
        $latestVisit = $playerGame->visits->last();
        $settled = $playerGame->gameSession->status === 'closed'
            || ($latestVisit !== null && $latestVisit->cashout !== null);
        $change = $settled
            ? (int) $cashouts->sum('ending_chips') - $credit - (int) $cashouts->sum('cash_paid')
            : null;

        return [
            'total_cash_buy_in' => $cash,
            'total_credit_buy_in' => $credit,
            'total_buy_in' => $cash + $credit,
            'total_cashout' => $cashouts->isEmpty() ? null : (int) $cashouts->sum('ending_chips'),
            'total_cash_paid' => $cashouts->isEmpty() ? null : (int) $cashouts->sum('cash_paid'),
            'book_balance_change' => $change,
            'book_balance_change_label' => $change === null ? '—'
                : ($change > 0 ? '+' : ($change < 0 ? '−' : '')).'$'.number_format(abs($change)),
            'purchases' => $this->purchaseHistory($playerGame),
            'participation_status' => $latestVisit === null ? 'No visits'
                : ($latestVisit->cashout === null ? 'Still playing' : 'Cashed out'),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function purchaseHistory(PlayerGame $playerGame): Collection
    {
        $purchases = collect();
        foreach ($playerGame->visits as $visit) {
            // A corrected purchase may gain a portion later; its original ID
            // keeps the whole purchase in its original position.
            $groups = $visit->buyIns->sortBy('id')->groupBy('submission_id')->values();
            foreach ($groups as $index => $group) {
                $first = $group->first();
                $cash = (int) $group->where('type', 'cash')->sum('amount');
                $credit = (int) $group->where('type', 'credit')->sum('amount');
                $purchases->push([
                    'id' => $first->id,
                    'label' => $index === 0
                        ? ($visit->visit_number === 1 ? 'Initial buy-in' : 'Return buy-in')
                        : 'Top-up',
                    'visit_number' => $visit->visit_number,
                    'cash' => $cash,
                    'credit' => $credit,
                    'total' => $cash + $credit,
                ]);
            }
        }

        return $purchases;
    }
}
