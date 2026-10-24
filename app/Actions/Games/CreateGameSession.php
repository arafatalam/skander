<?php

namespace App\Actions\Games;

use App\Models\GameSession;
use App\Models\GameSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
     * @param  array{min_buy_in: int, max_buy_in: int}  $confirmedDefaults
     * @param  array{min_buy_in: int, max_buy_in: int}|null  $customLimits
     */
    public function create(
        string $gameDate,
        User $admin,
        string $confirmedGameName,
        array $confirmedDefaults,
        ?array $customLimits = null,
    ): GameSession {
        Gate::forUser($admin)->authorize('create', GameSession::class);

        return DB::transaction(function () use ($gameDate, $admin, $confirmedGameName, $confirmedDefaults, $customLimits) {
            $this->lockAdmin($admin);
            $details = $this->nextGameDetails($gameDate);
            $this->ensureGameConfirmationIsCurrent($gameDate, $confirmedGameName, $details['game_name']);
            $limits = $this->resolveBuyInLimits($confirmedDefaults, $customLimits);

            return $this->saveGameSession($gameDate, $admin, $details, $limits);
        });
    }

    private function lockAdmin(User $admin): void
    {
        // Make simultaneous requests from the Admin wait their turn.
        User::query()
            ->whereKey($admin->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureGameConfirmationIsCurrent(
        string $gameDate,
        string $confirmedGameName,
        string $nextGameName,
    ): void {
        $dateChanged = $gameDate !== Carbon::today()->toDateString();
        $nameChanged = $confirmedGameName !== $nextGameName;

        if ($dateChanged || $nameChanged) {
            throw ValidationException::withMessages([
                'game_name' => 'The game details have changed. Refresh the page and confirm the new game name.',
            ]);
        }
    }

    /**
     * @param  array{min_buy_in: int, max_buy_in: int}  $confirmedDefaults
     * @param  array{min_buy_in: int, max_buy_in: int}|null  $customLimits
     * @return array{min_buy_in: int, max_buy_in: int}
     */
    private function resolveBuyInLimits(array $confirmedDefaults, ?array $customLimits): array
    {
        if ($customLimits !== null) {
            return $customLimits;
        }

        $settings = $this->lockGameSettings();
        $this->ensureDefaultLimitsAreCurrent($settings, $confirmedDefaults);

        return [
            'min_buy_in' => $settings->default_min_buy_in,
            'max_buy_in' => $settings->default_max_buy_in,
        ];
    }

    private function lockGameSettings(): GameSetting
    {
        $settings = GameSetting::houseDefaults();

        return GameSetting::query()
            ->whereKey($settings->getKey())
            ->sharedLock()
            ->firstOrFail();
    }

    /**
     * @param  array{min_buy_in: int, max_buy_in: int}  $confirmedDefaults
     */
    private function ensureDefaultLimitsAreCurrent(GameSetting $settings, array $confirmedDefaults): void
    {
        if (
            $confirmedDefaults['min_buy_in'] !== $settings->default_min_buy_in
            || $confirmedDefaults['max_buy_in'] !== $settings->default_max_buy_in
        ) {
            throw ValidationException::withMessages([
                'default_limits' => 'The default buy-in limits have changed. Refresh the page and confirm the new limits.',
            ]);
        }
    }

    /**
     * @param  array{game_number: int, game_name: string}  $details
     * @param  array{min_buy_in: int, max_buy_in: int}  $limits
     */
    private function saveGameSession(string $gameDate, User $admin, array $details, array $limits): GameSession
    {
        return GameSession::create([
            'game_date' => $gameDate,
            'game_number' => $details['game_number'],
            'game_name' => $details['game_name'],
            'status' => 'open',
            'min_buy_in' => $limits['min_buy_in'],
            'max_buy_in' => $limits['max_buy_in'],
            'created_by' => $admin->getKey(),
        ]);
    }
}
