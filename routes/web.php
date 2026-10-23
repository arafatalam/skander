<?php

use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerGameController;
use App\Models\GameSession;
use App\Models\GameSetting;
use App\Models\Player;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'index'])
            ->name('players')
            ->can('viewAny', Player::class);

        Route::get('create', [PlayerController::class, 'create'])
            ->name('players.create')
            ->can('create', Player::class);

        Route::post('/', [PlayerController::class, 'store'])
            ->name('players.store')
            ->can('create', Player::class);
    });

    Route::prefix('games')->group(function () {
        Route::get('/', [GameSessionController::class, 'index'])
            ->name('games.index')
            ->can('viewAny', GameSession::class);

        Route::get('create', [GameSessionController::class, 'create'])
            ->name('games.create')
            ->can('create', GameSession::class);

        Route::post('/', [GameSessionController::class, 'store'])
            ->name('games.store')
            ->can('create', GameSession::class);
    });

    Route::get('player-games/{playerGame}', [PlayerGameController::class, 'show'])
        ->name('player-games.show')
        ->can('view', 'playerGame');

    Route::get('settings/game', [GameSettingController::class, 'edit'])
        ->name('game-settings.edit')
        ->can('view', GameSetting::class);

    Route::patch('settings/game', [GameSettingController::class, 'update'])
        ->name('game-settings.update')
        ->can('update', GameSetting::class);

    Route::get('game-settings', [GameSettingController::class, 'editStandalone'])
        ->name('game-settings.standalone')
        ->can('view', GameSetting::class);

});

require __DIR__.'/settings.php';
