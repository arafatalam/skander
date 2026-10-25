<?php

use App\Actions\Corrections\RecordCorrection;
use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Payments\CorrectPayment;
use App\Actions\Players\CalculatePlayerBalance;
use App\Actions\Players\QueryPlayerBalances;
use App\Models\GameSession;
use App\Models\Payment;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(23, 30));
});

function dashboardGame(User $admin, string $date, int $number = 1): GameSession
{
    return GameSession::create([
        'game_date' => $date, 'game_number' => $number, 'game_name' => $date.'_'.$number,
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $admin->id,
    ]);
}

function dashboardPayment(User $admin, Player $player, int $amount, string $direction): Payment
{
    return Payment::create(['submission_id' => (string) Str::uuid(), 'player_id' => $player->id,
        'amount' => $amount, 'direction' => $direction, 'paid_at' => '2026-10-20', 'created_by' => $admin->id]);
}

test('dashboard is admin only and empty sections return zero totals', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'player']))->get(route('dashboard'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')->where('overview.outstanding.collect.total', 0)->where('overview.outstanding.pay.total', 0)
        ->where('overview.income.total', 0)->has('overview.open_games', 0)->has('overview.recent_games', 0));
});

test('dashboard balances use effective events include inactive players and rank top five without writes', function () {
    for ($i = 1; $i <= 7; $i++) {
        $player = Player::create(['name' => 'Collector '.$i]);
        $player->forceFill(['is_active' => $i !== 7, 'balance' => 99999])->save();
        dashboardPayment($this->admin, $player, $i * 100, 'house_pays_player');
    }
    $paid = Player::create(['name' => 'House owes']);
    $payment = dashboardPayment($this->admin, $paid, 300, 'house_collects_from_player');
    Player::create(['name' => 'Settled']);
    $before = Player::all()->toJson();
    $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('overview.outstanding.collect.total', 2800)->where('overview.outstanding.collect.count', 7)
        ->has('overview.outstanding.collect.players', 5)->where('overview.outstanding.collect.players.0.name', 'Collector 7')
        ->where('overview.outstanding.collect.players.4.amount', 300)->where('overview.outstanding.pay.total', 300)
        ->where('overview.outstanding.pay.players.0.url', route('players.show', ['player' => $paid->id, 'from' => 'dashboard'])));
    expect(Player::all()->toJson())->toBe($before);
    // The read follows the current effective event, never an old stored player balance.
    app(CorrectPayment::class)->correct($payment, $this->admin, [
        'amount' => 450, 'direction' => 'house_collects_from_player', 'paid_at' => '2026-10-01',
        'revision' => 0, 'submission_id' => (string) Str::uuid(), 'reason' => 'Correct amount and date',
    ]);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.outstanding.pay.total', 450));
});

test('dashboard month uses game dates and open games count only latest open visits', function () {
    $player = Player::create(['name' => 'Returning']);
    $open = dashboardGame($this->admin, '2026-09-30');
    $participant = app(CreateBuyIn::class)->create($open, $player, $this->admin, 200, 100);
    app(CreateCashout::class)->create($open, $player, $this->admin, 200, 50, $participant->latestVisit->id);
    app(CreateBuyIn::class)->create($open, $player, $this->admin, 200, 0);
    $out = Player::create(['name' => 'Already out']);
    $participant = app(CreateBuyIn::class)->create($open, $out, $this->admin, 100, 0);
    app(CreateCashout::class)->create($open, $out, $this->admin, 0, 0, $participant->latestVisit->id);
    dashboardPayment($this->admin, $player, 25, 'house_pays_player');
    dashboardPayment($this->admin, $player, 10, 'house_collects_from_player');
    expect((int) app(QueryPlayerBalances::class)->query()->findOrFail($player->id)->getAttribute('book_balance'))
        ->toBe(app(CalculatePlayerBalance::class)->calculate($player))->toBe(35);
    $newer = dashboardGame($this->admin, '2026-10-24', 2);
    foreach (['2026-09-29', '2026-10-01', '2026-10-31', '2026-11-01', '2026-10-15', '2026-10-16'] as $date) {
        $game = dashboardGame($this->admin, $date);
        app(CreateBuyIn::class)->create($game, Player::create(['name' => 'Closed '.$date]), $this->admin, 100, 0);
        app(CloseGameSession::class)->close($game, $this->admin);
    }
    $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('overview.date', 'Saturday, 24 October 2026')->where('overview.income.total', 400)->where('overview.income.count', 4)
        ->where('overview.income.url', route('games.index', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31', 'status' => 'closed']))
        ->has('overview.recent_games', 5)->where('overview.recent_games.0.name', '2026-11-01_1')
        ->has('overview.open_games', 2)->where('overview.open_games.0.id', $newer->id)
        ->where('overview.open_games.1.still_playing', 1)->where('overview.open_games.1.totals.income', null));
    $october = GameSession::where('game_name', '2026-10-15_1')->firstOrFail();
    app(RecordCorrection::class)->record($this->admin, [
        'kind' => 'total_tips', 'game_id' => $october->id,
        'amount' => 25, 'revision' => 0, 'submission_id' => (string) Str::uuid(), 'reason' => 'Correct tips',
    ]);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.income.total', 375));
    $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.month', 'November')->where('overview.income.total', 100));
});

test('owing filters paginate include inactive players preserve search and return context', function () {
    for ($i = 1; $i <= 23; $i++) {
        $player = Player::create(['name' => sprintf('Player %02d', $i)]);
        if ($i === 23) {
            $player->forceFill(['is_active' => false])->save();
        }
        dashboardPayment($this->admin, $player, $i * 10, 'house_pays_player');
    }
    $pay = Player::create(['name' => 'A creditor']);
    dashboardPayment($this->admin, $pay, 100, 'house_collects_from_player');
    $zero = Player::create(['name' => 'A settled']);
    $this->actingAs($this->admin)->get(route('players', ['owing' => 'collect']))->assertInertia(fn (Assert $page) => $page
        ->has('players.data', 20)->where('players.total', 23)->where('players.data.0.name', 'Player 23')
        ->where('players.data.0.is_active', false)->where('players.data.0.balance', -230)
        ->where('players.next_page_url', fn ($url) => str_contains($url, 'owing=collect') && str_contains($url, 'page=2')));
    $filters = ['search' => 'Player', 'owing' => 'collect', 'page' => 2];
    $this->get(route('players', $filters))->assertInertia(fn (Assert $page) => $page->has('players.data', 3)
        ->where('players.data.0.name', 'Player 03')->where('players.current_page', 2));
    $this->get(route('players.show', ['player' => $player->id, ...$filters]))->assertInertia(fn (Assert $page) => $page
        ->where('backUrl', route('players', $filters))->where('backLabel', 'Back to players'));
    $this->get(route('players.show', ['player' => $pay->id, 'from' => 'dashboard']))->assertInertia(fn (Assert $page) => $page
        ->where('backUrl', route('dashboard'))->where('backLabel', 'Back to dashboard'));
    $this->get(route('players', ['owing' => 'pay']))->assertInertia(fn (Assert $page) => $page->has('players.data', 1)->where('players.data.0.id', $pay->id));
    $this->get(route('players', ['owing' => 'all']))->assertInertia(fn (Assert $page) => $page->where('players.total', 25)->where('players.data.0.id', $pay->id)->where('players.data.1.id', $zero->id));
    $this->get(route('players', ['owing' => 'invalid']))->assertSessionHasErrors('owing');
    $this->get(route('players', ['page' => 0]))->assertSessionHasErrors('page');
    $this->get(route('players.show', ['player' => $pay->id, 'from' => 'https://example.com']))->assertSessionHasErrors('from');
    $this->actingAs(User::factory()->create(['role' => 'player']))->get(route('players', ['owing' => 'collect']))->assertForbidden();
});
