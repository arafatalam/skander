<?php

namespace App\Actions\Games;

use App\Models\PlayerGame;

class CalculateGameBalance
{
    public function calculate(PlayerGame $participant): int
    {
        $participant->loadMissing(['buyIns', 'visits.cashout']);

        $balance = $participant->opening_balance;

        foreach ($participant->buyIns as $buyIn) {
            if ($buyIn->type === 'credit') {
                $balance -= $buyIn->amount;
            }
        }

        foreach ($participant->visits as $visit) {
            if ($visit->cashout !== null) {
                $balance += $visit->cashout->ending_chips - $visit->cashout->cash_paid;
            }
        }

        return $balance;
    }
}
