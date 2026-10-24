<?php

use App\Http\Controllers\BuyInController;
use App\Http\Controllers\CashoutController;
use App\Http\Controllers\CorrectionHistoryController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\GameSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerGameController;
use App\Models\BuyIn;
use App\Models\Cashout;
use App\Models\GameSession;
use App\Models\GameSetting;
use App\Models\Payment;
use App\Models\Player;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'index'])
            ->name('players')
            ->can('viewAny', Player::class);

        Route::get('{player}', [PlayerController::class, 'show'])
            ->name('players.show')
            ->whereNumber('player')
            ->can('view', 'player');

        Route::get('create', [PlayerController::class, 'create'])
            ->name('players.create')
            ->can('create', Player::class);

        Route::post('/', [PlayerController::class, 'store'])
            ->name('players.store')
            ->can('create', Player::class);

        Route::get('{player}/edit', [PlayerController::class, 'edit'])
            ->name('players.edit')
            ->whereNumber('player')
            ->can('update', 'player');

        Route::patch('{player}/deactivate', [PlayerController::class, 'deactivate'])
            ->name('players.deactivate')
            ->whereNumber('player')
            ->can('deactivate', 'player');

        Route::patch('{player}', [PlayerController::class, 'update'])
            ->name('players.update')
            ->whereNumber('player')
            ->can('update', 'player');
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

        Route::get('{gameSession}/totals', [GameSessionController::class, 'editTotals'])
            ->name('games.totals.edit')
            ->can('recordTotals', 'gameSession');

        Route::post('{gameSession}/totals', [GameSessionController::class, 'recordTotals'])
            ->name('games.totals.store')
            ->can('recordTotals', 'gameSession');

        Route::post('{gameSession}/close', [GameSessionController::class, 'close'])
            ->name('games.close')
            ->can('close', 'gameSession');

        Route::get('{gameSession}', [GameSessionController::class, 'show'])
            ->name('games.show')
            ->can('view', 'gameSession');

    });

    Route::get('buy-ins/{buyIn}/corrections', [CorrectionHistoryController::class, 'buyIn'])
        ->name('buy-ins.corrections')
        ->whereNumber('buyIn')
        ->can('viewCorrections', 'buyIn');

    Route::get('cashouts/{cashout}/corrections', [CorrectionHistoryController::class, 'cashout'])
        ->name('cashouts.corrections')
        ->whereNumber('cashout')
        ->can('viewCorrections', 'cashout');

    Route::get('payments/{payment}/corrections', [CorrectionHistoryController::class, 'payment'])
        ->name('payments.corrections')
        ->whereNumber('payment')
        ->can('viewCorrections', 'payment');

    Route::get('games/{gameSession}/corrections/{field}', [CorrectionHistoryController::class, 'gameTotal'])
        ->name('games.corrections')
        ->whereNumber('gameSession')
        ->whereIn('field', ['total_tips', 'total_cost'])
        ->can('viewCorrections', 'gameSession');

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

    Route::get('buy-ins/create', [BuyInController::class, 'create'])
        ->name('buy-ins.create')
        ->can('create', BuyIn::class);

    Route::post('buy-ins', [BuyInController::class, 'store'])
        ->name('buy-ins.store')
        ->can('create', BuyIn::class);

    Route::get('cashouts/create', [CashoutController::class, 'create'])
        ->name('cashouts.create')
        ->can('create', Cashout::class);

    Route::post('cashouts', [CashoutController::class, 'store'])
        ->name('cashouts.store')
        ->can('create', Cashout::class);

    Route::get('cashouts/{cashout}/edit', [CashoutController::class, 'edit'])
        ->name('cashouts.edit')
        ->whereNumber('cashout')
        ->can('correct', 'cashout');

    Route::patch('cashouts/{cashout}', [CashoutController::class, 'update'])
        ->name('cashouts.update')
        ->whereNumber('cashout')
        ->can('correct', 'cashout');

    Route::get('payments/create', [PaymentController::class, 'create'])
        ->name('payments.create')
        ->can('create', Payment::class);

    Route::post('payments', [PaymentController::class, 'store'])
        ->name('payments.store')
        ->can('create', Payment::class);

    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])
        ->name('payments.edit')
        ->whereNumber('payment')
        ->can('correct', 'payment');

    Route::patch('payments/{payment}', [PaymentController::class, 'update'])
        ->name('payments.update')
        ->whereNumber('payment')
        ->can('correct', 'payment');

});

require __DIR__.'/settings.php';
