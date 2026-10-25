<?php

namespace App\Actions\Corrections;

use App\Models\BuyIn;
use App\Models\Cashout;
use App\Models\Correction;
use App\Models\GameSession;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GetCorrectionHistory
{
    /** @return array<string, mixed> */
    public function get(BuyIn|Cashout|Payment|GameSession $record, ?string $field = null): array
    {
        return $record instanceof GameSession
            ? $this->gameTotalHistory($record, $field)
            : $this->eventHistory($record);
    }

    /** @return array<string, mixed> */
    private function gameTotalHistory(GameSession $game, ?string $field): array
    {
        abort_unless(in_array($field, ['total_tips', 'total_cost'], true), 404);
        $corrections = $game->totalCorrections()->where('field', $field)
            ->reorder('revision')->with('correctedBy')->get();
        $fields = ['amount' => $field === 'total_tips' ? 'Tips' : 'Game cost'];
        $actor = $field === 'total_tips' ? $game->tipsRecordedBy : $game->costRecordedBy;
        $time = $game->getAttribute($field === 'total_tips' ? 'tips_recorded_at' : 'cost_recorded_at');

        return [
            'title' => $game->game_name.' · '.$fields['amount'],
            'back_url' => route('games.show', $game),
            'record_id' => $game->id,
            'source' => null,
        ] + $this->historyValues($fields, ['amount' => $game->getAttribute($field)], $corrections, $actor?->name, $time);
    }

    /** @return array<string, mixed> */
    private function eventHistory(BuyIn|Cashout|Payment $event): array
    {
        $fields = match (true) {
            $event instanceof BuyIn => ['amount' => 'Amount', 'type' => 'Buy-in type'],
            $event instanceof Cashout => ['ending_chips' => 'Ending chips', 'cash_paid' => 'Cash paid'],
            default => ['amount' => 'Amount', 'direction' => 'Direction', 'paid_at' => 'Payment date', 'note' => 'Note'],
        };
        $player = match (true) {
            $event instanceof BuyIn => $event->playerGame->player,
            $event instanceof Cashout => $event->playerGameVisit->playerGame->player,
            default => $event->player,
        };
        $kind = match (true) {
            $event instanceof BuyIn => 'Buy-in',
            $event instanceof Cashout => 'Cashout',
            default => 'Payment',
        };

        return [
            'title' => $player->name.' · '.$kind.' #'.$event->id,
            'back_url' => route('players.show', $player),
            'record_id' => $event->id,
            'source' => $event instanceof Cashout ? $event->creation_method : null,
        ] + $this->historyValues(
            $fields, $event->only(array_keys($fields)),
            $event->corrections()->with('correctedBy')->get(),
            $event->createdBy?->name, $event->created_at,
        );
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $current
     * @param  Collection<int, covariant Correction>  $corrections
     * @return array<string, mixed>
     */
    private function historyValues(array $fields, array $current, Collection $corrections, ?string $actor, ?CarbonInterface $time): array
    {
        return [
            'original' => [
                'actor' => $actor,
                'recorded_at' => $time?->toIso8601String(),
                'values' => $this->values($fields, $current, $corrections->first(), 'previous_'),
            ],
            'current' => $this->values($fields, $current),
            'revisions' => $this->revisions($corrections, $fields),
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $current
     * @return list<array{label: string, value: string}>
     */
    private function values(array $fields, array $current, ?Model $correction = null, string $prefix = ''): array
    {
        $values = [];
        foreach ($fields as $field => $label) {
            $value = $correction !== null
                ? $correction->getAttribute($prefix.$field)
                : ($current[$field] ?? null);
            $values[] = ['label' => $label, 'value' => $this->formatValue($field, $value)];
        }

        return $values;
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'Not recorded';
        }
        if (in_array($field, ['amount', 'ending_chips', 'cash_paid'], true)) {
            return '$'.number_format($value);
        }
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if ($field === 'note') {
            return $value;
        }

        return match ($value) {
            'cash' => 'Cash',
            'credit' => 'Credit',
            'house_pays_player' => 'House pays player',
            'house_collects_from_player' => 'House collects from player',
            default => (string) $value,
        };
    }

    /**
     * @param  Collection<int, covariant Correction>  $corrections
     * @param  array<string, string>  $fields
     * @return list<array<string, mixed>>
     */
    private function revisions(Collection $corrections, array $fields): array
    {
        $revisions = [];
        foreach ($corrections as $correction) {
            $revisions[] = [
                'id' => $correction->id,
                'revision' => $correction->revision,
                'actor' => $correction->correctedBy?->name,
                'corrected_at' => $correction->corrected_at->toIso8601String(),
                'reason' => $correction->reason,
                'before' => $this->values($fields, [], $correction, 'previous_'),
                'after' => $this->values($fields, [], $correction, 'new_'),
            ];
        }

        return $revisions;
    }
}
