<?php

use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Payments\RecordPayment;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->player = Player::create(['name' => 'History Player']);
    $this->game = GameSession::create([
        'game_date' => '2026-10-24', 'game_number' => 1, 'game_name' => '24_OCT_2026_1',
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $this->admin->id,
    ]);
});

test('only admins can deactivate players', function () {
    $url = route('players.deactivate', $this->player);
    $this->patch($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'user']))->patch($url)->assertForbidden();
    expect($this->player->fresh()->is_active)->toBeTrue();
    $this->actingAs($this->admin)->patch($url)->assertRedirect(route('players.show', $this->player));
    expect($this->player->fresh()->is_active)->toBeFalse();
    $this->patch($url)->assertRedirect();
    $this->assertDatabaseCount('players', 1);
});

test('deactivation preserves all history and removes only the buy-in choice', function () {
    $participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 200, 100);
    app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 300, 0, $participant->latestVisit->id);
    app(RecordPayment::class)->record($this->player, $this->admin, 50, 'house_pays_player', '2026-10-24', (string) Str::uuid());
    $tables = ['player_games', 'player_game_visits', 'buy_ins', 'cashouts', 'payments'];
    $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()]);
    $balance = $this->player->fresh()->balance;
    $this->actingAs($this->admin)->patch(route('players.deactivate', $this->player))->assertRedirect();
    expect($this->player->fresh()->balance)->toBe($balance);
    foreach ($tables as $table) {
        expect(DB::table($table)->get()->toJson())->toBe($before[$table]);
    }
    $this->get(route('players.show', $this->player))->assertInertia(fn (Assert $page) => $page
        ->component('players/Show')->where('player.is_active', false)->has('games', 1)->has('payments', 1));
    $this->get(route('players'))->assertInertia(fn (Assert $page) => $page->has('players', 1)->where('players.0.is_active', false));
    $this->get(route('buy-ins.create'))->assertInertia(fn (Assert $page) => $page->has('players', 0));
    $this->get(route('payments.create'))->assertInertia(fn (Assert $page) => $page->has('players', 1));
});

test('stale buy-ins cannot create initial visits top-ups or returns', function (string $state) {
    if ($state !== 'initial') {
        $participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 100, 0);
        if ($state === 'return') {
            app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 100, 0, $participant->latestVisit->id);
        }
    }
    $this->actingAs($this->admin)->patch(route('players.deactivate', $this->player));
    $before = DB::table('players')->get()->toJson();
    $counts = collect(['player_games', 'player_game_visits', 'buy_ins'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
    $this->post(route('buy-ins.store'), [
        'game_session_id' => $this->game->id, 'player_id' => $this->player->id,
        'cash_amount' => 200, 'credit_amount' => 100,
    ])->assertSessionHasErrors('player_id');
    expect(DB::table('players')->get()->toJson())->toBe($before);
    foreach ($counts as $table => $count) {
        $this->assertDatabaseCount($table, $count);
    }
})->with(['initial', 'top-up', 'return']);

test('an inactive player can finish an open visit and settle their balance', function () {
    $participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 100, 0);
    $this->actingAs($this->admin)->patch(route('players.deactivate', $this->player));
    app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 200, 0, $participant->latestVisit->id);
    app(RecordPayment::class)->record($this->player, $this->admin, 200, 'house_pays_player', '2026-10-24', (string) Str::uuid());
    expect($this->player->fresh()->balance)->toBe(0)->and($this->player->fresh()->is_active)->toBeFalse();
});
