<?php

namespace App\Actions\Corrections;

use App\Models\Cashout;
use App\Models\CorrectionSubmission;
use App\Models\GameSession;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerGame;
use Illuminate\Support\Collection;

class GetCorrectionWorkspace
{
    public function __construct(private readonly GetCorrectionHistory $history, private readonly CheckHistoricalBuyIns $checks) {}

    /** @param array<string, mixed> $selection
     * @return array<string, mixed>
     */
    public function get(array $selection): array
    {
        $tab = $selection['tab'];
        $gameId = $selection['game_id'] ?? null;
        $playerId = $selection['player_id'] ?? null;
        $mode = $selection['mode'] ?? 'missing_player';
        $game = $gameId === null ? null : GameSession::whereKey($gameId)->firstOrFail();
        $players = Player::query()->orderBy('name')->orderBy('id');
        if ($tab === 'payments') {
            $players->has('payments');
        } elseif (in_array($tab, ['buy-ins', 'cashouts'], true)) {
            $players->whereHas('playerGames', fn ($q) => $q->where('game_session_id', $gameId)
                ->has($tab === 'buy-ins' ? 'buyIns' : 'visits.cashout'));
        } elseif ($tab === 'missing' && $mode !== 'missing_payment') {
            if ($mode === 'missing_player') {
                $players->whereDoesntHave('playerGames', fn ($q) => $q->where('game_session_id', $gameId));
            } else {
                $players->whereHas('playerGames', fn ($q) => $q->where('game_session_id', $gameId));
            }
        }
        $playerOptions = $players->get(['id', 'name', 'is_active']);
        if ($playerId !== null && ! $playerOptions->contains('id', (int) $playerId)) {
            // A completed missing-player addition may no longer appear in its selector.
            $playerId = null;
        }
        $participant = $playerId === null || $game === null ? null
            : PlayerGame::where('game_session_id', $game->id)->where('player_id', $playerId)->first();
        $additions = $playerId === null ? collect() : CorrectionSubmission::where('player_id', $playerId)
            ->where('kind', 'like', 'missing_%')->with('createdBy')->orderBy('id')->get();
        $records = [];
        if ($tab === 'payments' && $playerId !== null) {
            $records = Payment::where('player_id', $playerId)->orderByDesc('paid_at')->orderByDesc('id')->get()
                ->map(fn ($payment) => $this->payment($payment, $additions))->all();
        } elseif (in_array($tab, ['buy-ins', 'cashouts'], true) && $participant !== null) {
            foreach ($participant->visits()->with(['buyIns', 'cashout'])->get() as $visit) {
                if ($tab === 'cashouts' && $visit->cashout !== null) {
                    $records[] = $this->cashout($visit->cashout, $visit->visit_number, $additions);
                } elseif ($tab === 'buy-ins') {
                    foreach ($visit->buyIns->sortBy('id')->groupBy('submission_id')->values() as $index => $group) {
                        $first = $group->first();
                        $cash = (int) $group->where('type', 'cash')->sum('amount');
                        $credit = (int) $group->where('type', 'credit')->sum('amount');
                        $records[] = [
                            'key' => 'buy_in-'.$first->id, 'kind' => 'buy_in', 'target_id' => $first->id,
                            'title' => 'Visit '.$visit->visit_number.' · Buy-in '.($index + 1),
                            'visit_number' => $visit->visit_number,
                            'subtitle' => 'Recorded '.$first->created_at->toIso8601String(),
                            'values' => [$this->money('Cash', $cash), $this->money('Credit', $credit), $this->money('Total', $cash + $credit)],
                            'inputs' => ['cash_amount' => $cash, 'credit_amount' => $credit],
                            'revision' => (int) $group->max(fn ($row) => (int) $row->corrections()->max('revision')),
                            'histories' => $group->map(fn ($row) => ['label' => ucfirst($row->type).' portion'] + $this->history->get($row))->values()->all(),
                            'additions' => $this->additions($additions, 'buy_in', $first->id),
                        ];
                    }
                }
            }
        } elseif ($tab === 'totals' && $game !== null) {
            foreach (['total_tips' => 'Tips', 'total_cost' => 'Game cost'] as $field => $label) {
                $records[] = [
                    'key' => $field, 'kind' => $field, 'target_id' => $game->id,
                    'title' => $label, 'subtitle' => $game->game_name,
                    'values' => [$this->money($label, $game->getAttribute($field))],
                    'inputs' => ['amount' => $game->getAttribute($field)],
                    'revision' => (int) $game->totalCorrections()->where('field', $field)->max('revision'),
                    'histories' => [$this->history->get($game, $field)], 'additions' => [],
                ];
            }
        }

        return [
            'selection' => ['tab' => $tab, 'game_id' => $gameId, 'player_id' => $playerId, 'mode' => $mode],
            'games' => GameSession::orderByDesc('game_date')->orderByDesc('game_number')->get(['id', 'game_name', 'status']),
            'players' => $playerOptions,
            'records' => $records,
            'visits' => $participant?->visits()->with('cashout')->get()->map(fn ($visit) => [
                'id' => $visit->id, 'visit_number' => $visit->visit_number,
                'cashout_id' => $visit->cashout?->id,
                'automatic' => $visit->cashout?->creation_method === 'automatic',
            ])->all() ?? [],
            'sequenceVersion' => $this->checks->sequenceVersion($participant),
            'additions' => $additions->filter(fn ($entry) => $gameId === null || $entry->game_session_id === (int) $gameId)
                ->map(fn ($entry) => $this->additionSummary($entry))->values()->all(),
        ];
    }

    /** @param Collection<int, CorrectionSubmission> $additions
     * @return array<string, mixed>
     */
    private function payment(Payment $payment, Collection $additions): array
    {
        return [
            'key' => 'payment-'.$payment->id, 'kind' => 'payment', 'target_id' => $payment->id,
            'title' => $payment->paid_at->toDateString().' · '.($payment->direction === 'house_pays_player' ? 'House pays player' : 'House collects from player'),
            'subtitle' => 'Recorded '.$payment->created_at->toIso8601String(),
            'values' => [$this->money('Amount', $payment->amount)],
            'inputs' => $payment->only(['amount', 'direction', 'note']) + ['paid_at' => $payment->paid_at->toDateString()],
            'revision' => (int) $payment->corrections()->max('revision'),
            'histories' => [$this->history->get($payment)],
            'additions' => $this->additions($additions, 'payment', $payment->id),
        ];
    }

    /** @param Collection<int, CorrectionSubmission> $additions
     * @return array<string, mixed>
     */
    private function cashout(Cashout $cashout, int $visitNumber, Collection $additions): array
    {
        return [
            'key' => 'cashout-'.$cashout->id, 'kind' => 'cashout', 'target_id' => $cashout->id,
            'title' => 'Visit '.$visitNumber.' · '.ucfirst($cashout->creation_method).' cashout',
            'visit_number' => $visitNumber, 'subtitle' => 'Recorded '.$cashout->created_at->toIso8601String(),
            'values' => [$this->money('Cashout', $cashout->ending_chips), $this->money('Cash paid', $cashout->cash_paid)],
            'inputs' => $cashout->only(['ending_chips', 'cash_paid']),
            'revision' => (int) $cashout->corrections()->max('revision'),
            'histories' => [$this->history->get($cashout)], 'additions' => $this->additions($additions, 'cashout', $cashout->id),
        ];
    }

    /** @return array{label: string, value: string} */
    private function money(string $label, ?int $amount): array
    {
        return ['label' => $label, 'value' => $amount === null ? 'Not recorded' : '$'.number_format($amount)];
    }

    /** @param Collection<int, CorrectionSubmission> $entries
     * @return list<array<string, mixed>>
     */
    private function additions(Collection $entries, string $kind, int $id): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $added = $kind === 'payment'
                ? $entry->kind === 'missing_payment' && $entry->target_id === $id
                : $this->containsRecord($entry->after, $kind, $id) && ! $this->containsRecord($entry->before, $kind, $id);
            if ($added) {
                $result[] = $this->additionSummary($entry);
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $snapshot */
    private function containsRecord(array $snapshot, string $kind, int $id): bool
    {
        foreach ($snapshot['visits'] ?? [] as $visit) {
            if ($kind === 'cashout' && ($visit['cashout']['id'] ?? null) === $id) {
                return true;
            }
            if ($kind === 'buy_in') {
                foreach ($visit['buy_ins'] as $buyIn) {
                    if ($buyIn['id'] === $id) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function additionSummary(CorrectionSubmission $entry): array
    {
        return ['id' => $entry->id, 'kind' => str_replace('_', ' ', $entry->kind), 'actor' => $entry->createdBy->name,
            'recorded_at' => $entry->created_at->toIso8601String(), 'reason' => $entry->reason];
    }
}
