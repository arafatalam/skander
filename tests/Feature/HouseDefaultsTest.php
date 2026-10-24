<?php

use App\Actions\Games\CreateGameSession;
use App\Models\GameSession;
use App\Models\GameSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    GameSetting::query()->update(['id' => 4, 'default_min_buy_in' => 200, 'default_max_buy_in' => 800]);
});

test('create game and both settings pages use named defaults with an arbitrary id', function () {
    $this->actingAs($this->admin)->get(route('games.create'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('games/Create')
        ->where('defaultLimits.min_buy_in', 200)
        ->where('defaultLimits.max_buy_in', 800));

    foreach (['game-settings.edit', 'game-settings.standalone'] as $route) {
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('settings.default_min_buy_in', 200)
            ->where('settings.default_max_buy_in', 800));
    }

    $details = app(CreateGameSession::class)->nextGameDetails(today()->toDateString());
    $this->post(route('games.store'), [
        'game_date' => today()->toDateString(), 'game_name' => $details['game_name'],
        'use_custom_limits' => false, 'confirmed_min_buy_in' => 200, 'confirmed_max_buy_in' => 800,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $game = GameSession::sole();
    expect($game->min_buy_in)->toBe(200)->and($game->max_buy_in)->toBe(800);

    $this->patch(route('game-settings.update'), [
        'default_min_buy_in' => 300, 'default_max_buy_in' => 1000,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect(GameSetting::houseDefaults()->id)->toBe(4)
        ->and(GameSetting::houseDefaults()->default_min_buy_in)->toBe(300)
        ->and(GameSetting::count())->toBe(1)
        ->and($game->fresh()->min_buy_in)->toBe(200)
        ->and($game->fresh()->max_buy_in)->toBe(800);
});

test('missing defaults are initialized once when an admin opens create game', function () {
    GameSetting::query()->delete();
    $this->actingAs($this->admin)->get(route('games.create'))->assertOk();
    $this->get(route('games.create'))->assertOk();
    expect(GameSetting::count())->toBe(1)
        ->and(GameSetting::houseDefaults()->default_min_buy_in)->toBe(100)
        ->and(GameSetting::houseDefaults()->default_max_buy_in)->toBe(500);
});

test('changed defaults still reject an outdated create game confirmation', function () {
    $details = app(CreateGameSession::class)->nextGameDetails(today()->toDateString());
    $this->actingAs($this->admin)->post(route('games.store'), [
        'game_date' => today()->toDateString(), 'game_name' => $details['game_name'],
        'use_custom_limits' => false, 'confirmed_min_buy_in' => 100, 'confirmed_max_buy_in' => 500,
    ])->assertSessionHasErrors('default_limits');
    expect(GameSession::count())->toBe(0);
});

test('guests and non admins cannot access or change house defaults or create games', function () {
    GameSetting::query()->delete();
    foreach (['games.create', 'game-settings.edit', 'game-settings.standalone'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
    $this->post(route('games.store'), [])->assertRedirect(route('login'));
    $this->patch(route('game-settings.update'), [])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'player']));
    foreach (['games.create', 'game-settings.edit', 'game-settings.standalone'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
    $this->post(route('games.store'), [])->assertForbidden();
    $this->patch(route('game-settings.update'), [])->assertForbidden();
    expect(GameSetting::count())->toBe(0);
});

test('database prevents duplicate named house defaults', function () {
    expect(fn () => GameSetting::create([
        'key' => GameSetting::HOUSE_DEFAULTS_KEY,
        'default_min_buy_in' => 100, 'default_max_buy_in' => 500,
    ]))->toThrow(QueryException::class);
});

test('migration preserves existing settings with a different id', function () {
    $migration = require database_path('migrations/2026_10_24_160000_name_house_default_settings.php');
    $migration->down();
    $before = (array) DB::table('game_settings')->sole();
    $migration->up();
    $after = (array) DB::table('game_settings')->sole();
    expect($after['key'])->toBe('house_defaults');
    unset($after['key']);
    expect($after)->toBe($before);
});
