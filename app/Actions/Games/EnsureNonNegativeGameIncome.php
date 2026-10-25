<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use Illuminate\Validation\ValidationException;

class EnsureNonNegativeGameIncome
{
    public function __construct(private readonly GetGameTotals $totals) {}

    // Call inside the event transaction while holding the game lock.
    public function ensure(GameSession $game, bool $closing = false): void
    {
        if (! $closing && $game->status !== 'closed') {
            return;
        }

        if ($this->totals->income($this->totals->query()->findOrFail($game->id)) < 0) {
            throw ValidationException::withMessages([
                'income' => $closing
                    ? 'This game cannot be closed because its total income is negative.'
                    : 'This correction can’t be made because it makes the total income negative.',
            ]);
        }
    }
}
