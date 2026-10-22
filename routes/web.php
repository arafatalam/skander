<?php

use App\Http\Controllers\PlayerController;
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

});

require __DIR__.'/settings.php';
