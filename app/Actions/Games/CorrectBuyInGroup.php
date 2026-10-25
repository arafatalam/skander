<?php

namespace App\Actions\Games;

use App\Actions\Corrections\CheckHistoricalBuyIns;
use App\Models\BuyIn;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CorrectBuyInGroup
{
    /** Called inside RecordCorrection's locked transaction with validated input.
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function correct(BuyIn $buyIn, User $admin, array $data): array
    {
        Gate::forUser($admin)->authorize('correct', $buyIn);
        $rows = BuyIn::where('submission_id', $buyIn->submission_id)->orderBy('id')->lockForUpdate()->get();
        $revision = (int) $rows->max(fn ($row) => (int) $row->corrections()->max('revision'));
        if ($revision !== $data['revision']) {
            throw ValidationException::withMessages(['revision' => 'This buy-in has changed. Reload the list before correcting it.']);
        }
        if ($data['cash_amount'] + $data['credit_amount'] === 0) {
            throw ValidationException::withMessages(['cash_amount' => 'The combined buy-in must be greater than $0.']);
        }
        $before = $rows->map(fn ($row) => $row->only(['id', 'amount', 'type']))->all();
        $changes = [];
        foreach (['cash', 'credit'] as $type) {
            $row = $rows->firstWhere('type', $type);
            $amount = $data[$type.'_amount'];
            if ($row === null && $amount === 0) {
                continue;
            }
            $row ??= BuyIn::create([
                'player_game_id' => $buyIn->player_game_id,
                'player_game_visit_id' => $buyIn->player_game_visit_id,
                'submission_id' => $buyIn->submission_id,
                'type' => $type, 'amount' => 0, 'created_by' => $admin->id,
            ]);
            $changes[] = [$row, $row->amount];
            $row->update(['amount' => $amount]);
        }
        $warnings = app(CheckHistoricalBuyIns::class)->warnings($buyIn->playerGame);
        $reason = ($warnings === [] ? '' : implode("\n", $warnings)."\n\n").$data['reason'];
        foreach ($changes as [$row, $previous]) {
            $row->corrections()->create([
                'revision' => (int) $row->corrections()->max('revision') + 1,
                'submission_id' => (string) Str::uuid(),
                'previous_amount' => $previous, 'previous_type' => $row->type,
                'new_amount' => $row->amount, 'new_type' => $row->type,
                'reason' => $reason, 'corrected_by' => $admin->id, 'corrected_at' => now(),
            ]);
        }

        return [
            'before' => $before,
            'after' => BuyIn::where('submission_id', $buyIn->submission_id)->orderBy('id')->get(['id', 'amount', 'type'])->toArray(),
            'warnings' => $warnings,
        ];
    }
}
