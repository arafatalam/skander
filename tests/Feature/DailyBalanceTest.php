<?php

use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Games\GetGameDetails;
use App\Actions\Payments\CorrectPayment;
use App\Actions\Payments\RecordPayment;
use App\Actions\Players\CalculatePlayerBalance;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\User;
use Database\Seeders\DailyBalanceDemoSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function dailyTestGame(User $admin, Player $player, string $date, int $credit = 0, bool $close = true): PlayerGame
{
    $number = (int) GameSession::whereDate('game_date', $date)->max('game_number') + 1;
    $game = GameSession::create([
        'game_date' => $date, 'game_number' => $number, 'game_name' => "$date-$number",
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 1500, 'created_by' => $admin->id,
    ]);
    $participant = app(CreateBuyIn::class)->create($game, $player, $admin, 500, $credit);
    if ($close) {
        app(CloseGameSession::class)->close($game, $admin);
    }

    return $participant;
}

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(18, 0));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->player = Player::create(['name' => 'Daily test']);
    $this->balances = app(CalculatePlayerBalance::class);
});

test('daily balances include backdated payments in every same-day game and later dates', function () {
    dailyTestGame($this->admin, $this->player, '2026-10-21', 200);
    $thursday = dailyTestGame($this->admin, $this->player, '2026-10-22');
    $second = dailyTestGame($this->admin, $this->player, '2026-10-22');
    dailyTestGame($this->admin, $this->player, '2026-10-23');
    $id = (string) Str::uuid();
    $this->actingAs($this->admin)->post(route('payments.store'), [
        'player_id' => $this->player->id, 'amount' => 100, 'direction' => 'house_collects_from_player',
        'paid_at' => '2026-10-22', 'submission_id' => $id,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->balances->calculate($this->player, '2026-10-21'))->toBe(-200)
        ->and($this->balances->calculate($this->player, '2026-10-22'))->toBe(-100)
        ->and($this->balances->calculate($this->player, '2026-10-23'))->toBe(-100)
        ->and($this->player->fresh()->balance)->toBe(-100);
    foreach ([$thursday, $second] as $participant) {
        $details = app(GetGameDetails::class)->get($participant->gameSession);
        expect($details['players'][0]['daily_balance'])->toBe(-100);
        $this->get(route('player-games.show', $participant))->assertInertia(fn (Assert $page) => $page
            ->where('playerGame.daily_balance_label', 'Player owes house $100'));
    }
    app(RecordPayment::class)->record($this->player, $this->admin, 100, 'house_collects_from_player', '2026-10-22', $id);
    $this->assertDatabaseCount('payments', 1);
    expect($this->player->fresh()->balance)->toBe(-100);
});

test('date corrections move historical effect and preserve the current balance and audit', function () {
    dailyTestGame($this->admin, $this->player, '2026-10-21', 200);
    $payment = app(RecordPayment::class)->record($this->player, $this->admin, 100, 'house_collects_from_player', '2026-10-22', (string) Str::uuid());
    $input = ['amount' => 100, 'direction' => 'house_collects_from_player', 'paid_at' => '2026-10-23',
        'reason' => 'Actually Friday', 'revision' => 0, 'submission_id' => (string) Str::uuid()];
    $this->actingAs($this->admin)->patch(route('payments.update', $payment), $input)->assertSessionHasNoErrors()->assertRedirect();
    expect($this->balances->calculate($this->player, '2026-10-22'))->toBe(-200)
        ->and($this->balances->calculate($this->player, '2026-10-23'))->toBe(-100)
        ->and($this->balances->calculate($this->player, '2026-10-28'))->toBe(-100)
        ->and($this->player->fresh()->balance)->toBe(-100);
    app(CorrectPayment::class)->correct($payment, $this->admin, $input);
    $this->assertDatabaseCount('payment_corrections', 1);
    expect($payment->corrections()->first()->previous_paid_at->toDateString())->toBe('2026-10-22');
    $input['submission_id'] = (string) Str::uuid();
    expect(fn () => app(CorrectPayment::class)->correct($payment, $this->admin, $input))->toThrow(ValidationException::class);
    $input['revision'] = 1;
    $input['paid_at'] = '2026-10-21';
    $input['amount'] = 300;
    app(CorrectPayment::class)->correct($payment, $this->admin, $input);
    expect($this->balances->calculate($this->player, '2026-10-21'))->toBe(100)
        ->and($this->player->fresh()->balance)->toBe(100);
});

test('correction form and submission enforce admin policy and required reason', function () {
    dailyTestGame($this->admin, $this->player, '2026-10-21', 200);
    $payment = app(RecordPayment::class)->record($this->player, $this->admin, 100, 'house_collects_from_player', '2026-10-22', (string) Str::uuid());
    $this->get(route('payments.edit', $payment))->assertRedirect(route('login'));
    $this->patch(route('payments.update', $payment))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'user']))->get(route('payments.edit', $payment))->assertForbidden();
    $this->patch(route('payments.update', $payment))->assertForbidden();
    $this->actingAs($this->admin)->patch(route('payments.update', $payment), [
        'amount' => 100, 'direction' => 'house_collects_from_player', 'paid_at' => '2026-10-23',
        'reason' => '   ', 'revision' => 0, 'submission_id' => (string) Str::uuid(),
    ])->assertSessionHasErrors('reason');
    $this->assertDatabaseCount('payment_corrections', 0);
});

test('correction transaction rolls back event audit and balance on failure', function () {
    dailyTestGame($this->admin, $this->player, '2026-10-21', 200);
    $payment = app(RecordPayment::class)->record($this->player, $this->admin, 100, 'house_collects_from_player', '2026-10-22', (string) Str::uuid());
    DB::listen(function (QueryExecuted $query) {
        if (str_starts_with($query->sql, 'update "players"')) {
            throw new RuntimeException('Forced failure');
        }
    });
    expect(fn () => app(CorrectPayment::class)->correct($payment, $this->admin, [
        'amount' => 150, 'direction' => 'house_collects_from_player', 'paid_at' => '2026-10-23',
        'reason' => 'Correction', 'revision' => 0, 'submission_id' => (string) Str::uuid(),
    ]))->toThrow(RuntimeException::class, 'Forced failure');
    expect($payment->fresh()->amount)->toBe(100)->and($payment->fresh()->paid_at->toDateString())->toBe('2026-10-22')
        ->and($this->player->fresh()->balance)->toBe(-100);
    $this->assertDatabaseCount('payment_corrections', 0);
});

test('original balance examples remain correct using recorded earlier activity', function (bool $first) {
    $prior = dailyTestGame($this->admin, $this->player, '2026-10-20', $first ? 200 : 0, ! $first ? false : true);
    if (! $first) {
        app(CreateCashout::class)->create($prior->gameSession, $this->player, $this->admin, 200, 0, $prior->latestVisit->id);
        app(CloseGameSession::class)->close($prior->gameSession, $this->admin);
    }
    $game = dailyTestGame($this->admin, $this->player, '2026-10-21', $first ? 500 : 1000, false);
    app(CreateCashout::class)->create($game->gameSession, $this->player, $this->admin, $first ? 2000 : 700, $first ? 1000 : 0, $game->latestVisit->id);
    expect($this->balances->calculate($this->player, '2026-10-21'))->toBe($first ? 300 : -100)
        ->and($this->player->fresh()->balance)->toBe($first ? 300 : -100);
})->with([true, false]);

test('demo data preserves profiles and reconciles all current balances', function () {
    $users = DB::table('users')->get()->toJson();
    $profiles = DB::table('players')->get()->map(fn ($p) => collect((array) $p)->except(['balance', 'updated_at']))->toJson();
    $this->seed(DailyBalanceDemoSeeder::class);
    expect(DB::table('users')->get()->toJson())->toBe($users)
        ->and(DB::table('players')->get()->map(fn ($p) => collect((array) $p)->except(['balance', 'updated_at']))->toJson())->toBe($profiles);
    expect(GameSession::count())->toBe(7)->and(GameSession::where('status', 'open')->count())->toBe(1);
    foreach (Player::all() as $player) {
        expect($player->balance)->toBe($this->balances->calculate($player));
    }
    expect(Schema::getColumnListing('player_games'))->toBe(['id', 'player_id', 'game_session_id', 'created_by', 'created_at', 'updated_at']);
});
