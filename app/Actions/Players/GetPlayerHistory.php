<?php

namespace App\Actions\Players;

use App\Models\BuyIn;
use App\Models\Cashout;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;
use Illuminate\Support\Collection;

class GetPlayerHistory
{
    public function __construct(
        private readonly CalculatePlayerBalance $calculatePlayerBalance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(Player $player): array
    {
        return [
            'player' => $this->playerDetails($player),
            'games' => $this->gameHistory($player),
            'payments' => $this->paymentHistory($player),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerDetails(Player $player): array
    {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'is_active' => $player->is_active,
            'balance_label' => $this->formatBalanceForDisplay($player->balance),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function gameHistory(Player $player): Collection
    {
        return $player->playerGames()
            ->with(['gameSession', 'buyIns', 'visits.buyIns', 'visits.cashout'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(
                fn (PlayerGame $playerGame) => $this->gameDetails($playerGame),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function gameDetails(PlayerGame $playerGame): array
    {
        $gameBalance = $this->calculatePlayerBalance->calculate($playerGame->player, $playerGame->gameSession->game_date->toDateString());

        return [
            'id' => $playerGame->id,
            'game_name' => $playerGame->gameSession->game_name,
            'daily_balance_label' => $this->formatBalanceForDisplay($gameBalance),
            'visits' => $this->visitHistory($playerGame),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visitHistory(PlayerGame $playerGame): Collection
    {
        return $playerGame->visits->map(
            fn (PlayerGameVisit $visit) => $this->visitDetails($visit),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function visitDetails(PlayerGameVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'visit_number' => $visit->visit_number,
            'opened_at' => $visit->opened_at->toIso8601String(),
            'buy_ins' => $this->buyInHistory($visit),
            'cashout' => $this->cashoutDetails($visit->cashout),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buyInHistory(PlayerGameVisit $visit): Collection
    {
        return $visit->buyIns
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(
                fn (BuyIn $buyIn) => $this->buyInDetails($buyIn),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function buyInDetails(BuyIn $buyIn): array
    {
        return [
            'id' => $buyIn->id,
            'amount' => $buyIn->amount,
            'type' => $buyIn->type,
            'recorded_at' => $buyIn->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cashoutDetails(?Cashout $cashout): ?array
    {
        if ($cashout === null) {
            return null;
        }

        return [
            'id' => $cashout->id,
            'ending_chips' => $cashout->ending_chips,
            'cash_paid' => $cashout->cash_paid,
            'creation_method' => $cashout->creation_method,
            'cashed_out_at' => $cashout->cashed_out_at->toIso8601String(),
            'edit_url' => route('cashouts.edit', $cashout),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function paymentHistory(Player $player): Collection
    {
        return $player->payments()
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->map(
                fn (Payment $payment) => $this->paymentDetails($payment),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentDetails(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'direction' => $payment->direction,
            'direction_label' => $this->formatPaymentDirection($payment->direction),
            'paid_at' => $payment->paid_at->toDateString(),
            'recorded_at' => $payment->created_at->toIso8601String(),
            'note' => $payment->note,
            'edit_url' => route('payments.edit', $payment),
        ];
    }

    private function formatPaymentDirection(string $direction): string
    {
        return match ($direction) {
            'house_pays_player' => 'House paid player',
            'house_collects_from_player' => 'House collected from player',
            default => 'Unknown payment direction',
        };
    }

    private function formatBalanceForDisplay(int $balance): string
    {
        $amount = '$'.number_format(abs($balance));

        if ($balance > 0) {
            return 'House owes player '.$amount;
        }

        if ($balance < 0) {
            return 'Player owes house '.$amount;
        }

        return 'Nothing owed';
    }
}
