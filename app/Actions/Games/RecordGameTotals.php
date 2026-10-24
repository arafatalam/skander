<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordGameTotals
{
    public function record(
        GameSession $gameSession,
        User $admin,
        ?int $tips,
        ?int $cost,
    ): GameSession {
        Gate::forUser($admin)->authorize('recordTotals', $gameSession);

        $adminId = $admin->id;

        if (! $admin->exists || $adminId < 1) {
            throw new AuthorizationException('A saved Admin account is required to record totals.');
        }

        return DB::transaction(function () use (
            $gameSession,
            $adminId,
            $tips,
            $cost,
        ) {
            $game = GameSession::query()
                ->whereKey($gameSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $validated = $this->validateAmounts($tips, $cost);
            $tips = $validated['tips'];
            $cost = $validated['cost'];

            $amounts = [
                'total_tips' => $tips,
                'total_cost' => $cost,
            ];

            foreach ($amounts as $field => $amount) {
                if ($amount !== null && $game->{$field} !== null) {
                    throw ValidationException::withMessages([
                        $field => 'This total has already been recorded. Use the correction workflow to change it.',
                    ]);
                }
            }

            $recordedAt = now();

            if ($tips !== null) {
                $game->total_tips = $tips;
                $game->tips_recorded_by = $adminId;
                $game->tips_recorded_at = $recordedAt;
            }

            if ($cost !== null) {
                $game->total_cost = $cost;
                $game->cost_recorded_by = $adminId;
                $game->cost_recorded_at = $recordedAt;
            }

            $game->save();

            return $game;
        });
    }

    /**
     * @return array{tips: int<0, 2147483647>|null, cost: int<0, 2147483647>|null}
     */
    private function validateAmounts(?int $tips, ?int $cost): array
    {
        if ($tips === null && $cost === null) {
            throw ValidationException::withMessages([
                'total_tips' => 'Enter a tip total or a total cost.',
            ]);
        }

        if ($tips !== null && ($tips < 0 || $tips > 2147483647)) {
            throw ValidationException::withMessages([
                'total_tips' => 'Enter a whole-dollar amount between $0 and $2,147,483,647.',
            ]);
        }

        if ($cost !== null && ($cost < 0 || $cost > 2147483647)) {
            throw ValidationException::withMessages([
                'total_cost' => 'Enter a whole-dollar amount between $0 and $2,147,483,647.',
            ]);
        }

        return ['tips' => $tips, 'cost' => $cost];
    }
}
