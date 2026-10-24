<?php

namespace App\Actions\Players;

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeactivatePlayer
{
    public function deactivate(Player $player, User $admin): void
    {
        Gate::forUser($admin)->authorize('deactivate', $player);

        DB::transaction(function () use ($player) {
            $currentPlayer = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();

            if ($currentPlayer->is_active) {
                $currentPlayer->is_active = false;
                $currentPlayer->save();
            }
        });
    }
}
