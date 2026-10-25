<?php

namespace App\Actions\Corrections;

use App\Models\PlayerGame;

class CheckHistoricalBuyIns
{
    /** @return list<string> */
    public function warnings(PlayerGame $participant): array
    {
        $game = $participant->gameSession;
        $previousChips = null;
        $warnings = [];
        foreach ($participant->visits()->with(['buyIns', 'cashout'])->get() as $visit) {
            $groups = $visit->buyIns->sortBy('id')->groupBy('submission_id')->values();
            foreach ($groups as $index => $group) {
                $minimum = $game->min_buy_in;
                $maximum = $game->max_buy_in;
                if ($index === 0 && $previousChips !== null) {
                    $minimum = max($minimum, $previousChips);
                    $maximum = max($maximum, $previousChips);
                }
                $total = (int) $group->sum('amount');
                if ($total < $minimum || $total > $maximum) {
                    $limits = $minimum === $maximum ? 'exactly $'.number_format($minimum)
                        : '$'.number_format($minimum).' to $'.number_format($maximum);
                    $warnings[] = 'Warning: Visit '.$visit->visit_number.', buy-in '.($index + 1)
                        .' is $'.number_format($total).'; the permitted amount is '.$limits
                        .'. The historical amount is retained.';
                }
            }
            $previousChips = $visit->cashout?->ending_chips;
        }

        return $warnings;
    }

    public function sequenceVersion(?PlayerGame $participant): string
    {
        $sequence = $participant?->visits()->with(['buyIns', 'cashout'])->get()->map(fn ($visit) => [
            $visit->id, $visit->visit_number,
            $visit->buyIns->sortBy('id')->map(fn ($buyIn) => [$buyIn->id, $buyIn->amount, $buyIn->type])->values()->all(),
            $visit->cashout?->only(['id', 'ending_chips', 'cash_paid']),
        ])->all() ?? [];

        return hash('sha256', json_encode($sequence, JSON_THROW_ON_ERROR));
    }
}
