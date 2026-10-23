<?php

use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\PlayerController;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('players', [PlayerController::class, 'index'])
        ->name('players')
        ->can('viewAny', Player::class);

    Route::get('players/create', [PlayerController::class, 'create'])
        ->name('players.create')
        ->can('create', Player::class);

    Route::post('players', [PlayerController::class, 'store'])
        ->name('players.store')
        ->can('create', Player::class);

    Route::get('games/create', [GameSessionController::class, 'create'])
        ->name('games.create')
        ->can('create', GameSession::class);

    Route::post('games', [GameSessionController::class, 'store'])
        ->name('games.store')
        ->can('create', GameSession::class);

});

require __DIR__.'/settings.php';
