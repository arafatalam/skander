<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CorrectGameTotal
{
    /** Called inside RecordCorrection's locked transaction with validated input.
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function correct(GameSession $game, User $admin, array $data): array
    {
        Gate::forUser($admin)->authorize('correctTotals', $game);
        $field = $data['kind'];
        $revision = (int) $game->totalCorrections()->where('field', $field)->max('revision');
        if ($revision !== $data['revision']) {
            throw ValidationException::withMessages(['revision' => 'This total has changed. Reload the list before correcting it.']);
        }
        $before = $game->getAttribute($field);
        $game->totalCorrections()->create([
            'field' => $field, 'revision' => $revision + 1, 'submission_id' => $data['submission_id'],
            'previous_amount' => $before, 'new_amount' => $data['amount'],
            'reason' => $data['reason'], 'corrected_by' => $admin->id, 'corrected_at' => now(),
        ]);
        $game->update([$field => $data['amount']]);

        return ['before' => ['amount' => $before], 'after' => ['amount' => $data['amount']], 'warnings' => []];
    }
}
