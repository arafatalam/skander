<?php

namespace App\Http\Controllers;

use App\Actions\Corrections\GetCorrectionHistory;
use App\Models\BuyIn;
use App\Models\Cashout;
use App\Models\GameSession;
use App\Models\Payment;
use Inertia\Inertia;
use Inertia\Response;

class CorrectionHistoryController extends Controller
{
    public function buyIn(BuyIn $buyIn, GetCorrectionHistory $history): Response
    {
        return Inertia::render('corrections/Show', $history->get($buyIn));
    }

    public function cashout(Cashout $cashout, GetCorrectionHistory $history): Response
    {
        return Inertia::render('corrections/Show', $history->get($cashout));
    }

    public function payment(Payment $payment, GetCorrectionHistory $history): Response
    {
        return Inertia::render('corrections/Show', $history->get($payment));
    }

    public function gameTotal(GameSession $gameSession, string $field, GetCorrectionHistory $history): Response
    {
        return Inertia::render('corrections/Show', $history->get($gameSession, $field));
    }
}
