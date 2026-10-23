<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\GameSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGameSession
{
    /**
     * Calculate the next game number and name without saving.
     *
     * @return array{game_number: int, game_name: string}
     */
    public function nextGameDetails(string $gameDate): array
    {
        $lastNumber = GameSession::query()
            ->where('game_date', $gameDate)
            ->max('game_number');

        $gameNumber = ($lastNumber ?? 0) + 1;

        $formattedDate = Carbon::parse($gameDate)->format('d_M_Y');
        $gameName = strtoupper($formattedDate).'_'.$gameNumber;

        $details = [
            'game_number' => $gameNumber,
            'game_name' => $gameName,
        ];

        return $details;
    }

    /**
     * @param  array{min_buy_in: int, max_buy_in: int}|null  $customLimits
     */
    public function create(
        string $gameDate,
        User $admin,
        string $confirmedGameName,
        ?array $customLimits = null,
    ): GameSession {
        $game = DB::transaction(function () use ($gameDate, $admin, $confirmedGameName, $customLimits) {
            // Make simultaneous requests from the Admin wait their turn.
            User::query()
                ->whereKey($admin->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $details = $this->nextGameDetails($gameDate);

            $today = Carbon::today()->toDateString();

            $dateChanged = $gameDate !== $today;
            $nameChanged = $confirmedGameName !== $details['game_name'];

            if ($dateChanged || $nameChanged) {
                throw ValidationException::withMessages([
                    'game_name' => 'The game details have changed. Refresh the page and confirm the new game name.',
                ]);
            }

            if ($customLimits === null) {
                $settings = GameSetting::query()
                    ->whereKey(1)
                    ->sharedLock()
                    ->firstOrFail();

                $limits = [
                    'min_buy_in' => $settings->default_min_buy_in,
                    'max_buy_in' => $settings->default_max_buy_in,
                ];
            } else {
                $limits = $customLimits;
            }

            $gameSession = GameSession::create([
                'game_date' => $gameDate,
                'game_number' => $details['game_number'],
                'game_name' => $details['game_name'],
                'status' => 'open',
                'min_buy_in' => $limits['min_buy_in'],
                'max_buy_in' => $limits['max_buy_in'],
                'created_by' => $admin->getKey(),
            ]);

            return $gameSession;
        });

        return $game;
    }
}
