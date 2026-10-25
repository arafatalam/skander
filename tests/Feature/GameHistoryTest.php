<?php

use App\Actions\Corrections\CheckHistoricalBuyIns;
use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CorrectCashout;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Games\GetGameTotals;
use App\Actions\Games\RecordGameTotals;
use App\Models\Cashout;
use App\Models\CorrectionSubmission;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->player = Player::create(['name' => 'History player']);
    $this->game = GameSession::create([
        'game_date' => '2026-10-20', 'game_number' => 1, 'game_name' => '20_OCT_2026_1',
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $this->admin->id,
    ]);
    $this->participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 200, 100);
    $this->visit = $this->participant->visits()->firstOrFail();
});

test('games filters include both endpoints and preserve applied filters and page in navigation', function () {
    for ($day = 1; $day <= 31; $day++) {
        $date = sprintf('2026-10-%02d', $day);
        GameSession::create(['game_date' => $date, 'game_number' => 2,
            'game_name' => sprintf('%02d_OCT_2026_2', $day), 'status' => $day % 2 ? 'open' : 'closed',
            'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $this->admin->id]);
    }
    $this->actingAs($this->admin)->get(route('games.index', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31', 'status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->component('games/Index')->has('games.data', 20)
            ->where('games.total', 32)->where('games.data.0.game_name', '31_OCT_2026_2')->where('games.data.0.totals.income', null)
            ->where('games.next_page_url', fn ($url) => str_contains($url, 'from_date=2026-10-01') && str_contains($url, 'page=2')));
    $filters = ['from_date' => '2026-10-01', 'to_date' => '2026-10-31', 'status' => 'all', 'page' => 2];
    $this->get(route('games.index', $filters))->assertInertia(fn (Assert $page) => $page->has('games.data', 12)
        ->where('games.current_page', 2)->where('games.data.0.show_url', fn ($url) => str_contains($url, 'page=2') && str_contains($url, 'status=all')));
    $this->get(route('games.show', ['gameSession' => $this->game->id, ...$filters]))
        ->assertInertia(fn (Assert $page) => $page->where('backUrl', route('games.index', $filters)));
    $this->get(route('games.index', ['from_date' => '2026-10-20', 'to_date' => '2026-10-20']))
        ->assertInertia(fn (Assert $page) => $page->has('games.data', 2)->where('games.data.0.game_name', '20_OCT_2026_2')->where('games.data.1.id', $this->game->id));
    foreach ([['from_date' => '2026-10-31'], ['to_date' => '2026-10-01']] as $bound) {
        $this->get(route('games.index', $bound))->assertInertia(fn (Assert $page) => $page->has('games.data', 1));
    }
    foreach (['open' => 17, 'closed' => 15] as $status => $count) {
        $this->get(route('games.index', ['status' => $status]))->assertInertia(fn (Assert $page) => $page->where('games.total', $count));
    }
});

test('invalid dates return to current results and game history remains admin only', function () {
    $previous = route('games.index', ['status' => 'open', 'page' => 2]);
    $this->actingAs($this->admin)->from($previous)->get(route('games.index', ['from_date' => '2026-10-21', 'to_date' => '2026-10-20']))
        ->assertRedirect($previous)->assertSessionHasErrors(['to_date' => 'To date must be on or after From date.']);
    $this->get(route('games.index', ['from_date' => 'nonsense']))->assertSessionHasErrors('from_date');
    $this->get(route('games.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
    $this->actingAs(User::factory()->create(['role' => 'player']));
    $this->get(route('games.index'))->assertForbidden();
    $this->get(route('games.show', $this->game))->assertForbidden();
});

test('list and detail share effective totals across visits and income ignores cash paid', function () {
    app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 200, 50, $this->visit->id);
    app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 200, 0);
    app(RecordGameTotals::class)->record($this->game, $this->admin, 30, 20);
    $this->actingAs($this->admin)->get(route('games.show', $this->game))->assertInertia(fn (Assert $page) => $page->where('game.totals.income', null));
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $totals = ['buy_ins' => 500, 'ending_chips' => 200, 'cash_paid' => 50, 'income' => 250];
    $this->get(route('games.show', $this->game))->assertInertia(fn (Assert $page) => $page->where('game.totals', $totals));
    $this->get(route('games.index'))->assertInertia(fn (Assert $page) => $page->where('games.data.0.totals', $totals));
});

test('negative open income is allowed but closing rejects all changes until the records are corrected', function () {
    app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 500, 0, $this->visit->id);
    $stillPlaying = Player::create(['name' => 'Still playing']);
    app(CreateBuyIn::class)->create($this->game, $stillPlaying, $this->admin, 100, 0);
    app(RecordGameTotals::class)->record($this->game, $this->admin, 10, null);
    $this->actingAs($this->admin)->post(route('games.close', $this->game))->assertSessionHasErrors('income');
    expect($this->game->fresh()->status)->toBe('open')->and($this->game->fresh()->total_cost)->toBeNull()
        ->and($this->game->fresh()->closed_at)->toBeNull()->and(Cashout::count())->toBe(1);
    app(CreateBuyIn::class)->create($this->game, $stillPlaying, $this->admin, 110, 0);
    app(CloseGameSession::class)->close($this->game, $this->admin);
    app(CloseGameSession::class)->close($this->game, $this->admin);
    expect(app(GetGameTotals::class)->get($this->game)['income'])->toBe(0)
        ->and(Cashout::count())->toBe(2)->and($this->game->fresh()->total_cost)->toBe(0)
        ->and($this->game->fresh()->cost_recorded_by)->toBe($this->admin->id)
        ->and($this->game->fresh()->cost_recorded_at->equalTo($this->game->fresh()->closed_at))->toBeTrue();
});

test('closed corrections that make income negative roll back effective records history and balances', function (string $kind) {
    $cashout = app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 200, 50, $this->visit->id);
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $buyIn = $this->participant->buyIns()->firstOrFail();
    $input = ['kind' => $kind, 'game_id' => $this->game->id, 'player_id' => $this->player->id,
        'target_id' => $kind === 'cashout' ? $cashout->id : $buyIn->id, 'revision' => 0,
        'cash_amount' => 0, 'credit_amount' => 100, 'ending_chips' => 301, 'cash_paid' => 0,
        'amount' => 101, 'reason' => 'Check negative income', 'submission_id' => (string) Str::uuid()];
    $tables = ['buy_ins', 'cashouts', 'players', 'game_sessions', 'buy_in_corrections', 'cashout_corrections', 'game_total_corrections', 'correction_submissions'];
    $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    $before = $snapshot();
    $this->actingAs($this->admin)->post(route('record-correction.store'), $input)->assertSessionHasErrors([
        'income' => 'This correction can’t be made because it makes the total income negative.',
    ]);
    expect($snapshot())->toBe($before);
})->with(['buy_in', 'cashout', 'total_tips', 'total_cost']);

test('historical players and visits are rolled back together when their net income is negative', function (string $kind) {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $player = $kind === 'missing_player' ? Player::create(['name' => 'Forgotten']) : $this->player;
    $input = ['kind' => $kind, 'game_id' => $this->game->id, 'player_id' => $player->id, 'position' => 1,
        'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion($kind === 'missing_player' ? null : $this->participant),
        'reason' => 'Forgotten activity', 'submission_id' => (string) Str::uuid(),
        'visits' => [['buy_ins' => [['cash_amount' => 100, 'credit_amount' => 0]], 'ending_chips' => 401, 'cash_paid' => 200]]];
    $beforeVisits = DB::table('player_game_visits')->get()->toJson();
    $this->actingAs($this->admin)->post(route('record-correction.store'), $input)->assertSessionHasErrors('income');
    expect(DB::table('player_game_visits')->get()->toJson())->toBe($beforeVisits)
        ->and(CorrectionSubmission::count())->toBe(0)->and(Cashout::count())->toBe(1)
        ->and($player->fresh()->balance)->toBe($kind === 'missing_player' ? 0 : -100);
    $this->assertDatabaseCount('player_games', 1);
})->with(['missing_player', 'missing_visit']);

test('standalone cashout corrections enforce the same guard and zero income is allowed', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $input = ['ending_chips' => 301, 'cash_paid' => 301, 'revision' => 0, 'submission_id' => (string) Str::uuid(), 'reason' => 'Correct cashout'];
    $this->actingAs($this->admin)->patch(route('cashouts.update', $cashout), $input)->assertSessionHasErrors('income');
    expect($cashout->fresh()->ending_chips)->toBe(0)->and($cashout->corrections()->count())->toBe(0);
    app(CorrectCashout::class)->correct($cashout, $this->admin, array_replace($input, ['ending_chips' => 300, 'cash_paid' => 0]));
    expect(app(GetGameTotals::class)->get($this->game)['income'])->toBe(0)->and($this->player->fresh()->balance)->toBe(200);
});

test('migration defaults only absent closed totals and keeps recorded totals intact', function () {
    $this->game->update(['status' => 'closed', 'closed_by' => $this->admin->id, 'closed_at' => now(), 'total_tips' => 20]);
    $other = $this->game->replicate();
    $other->fill(['game_number' => 2, 'game_name' => '20_OCT_2026_2', 'status' => 'open', 'total_tips' => null])->save();
    $migration = require database_path('migrations/2026_10_25_020000_default_unrecorded_closed_game_totals_to_zero.php');
    $migration->up();
    expect($this->game->fresh()->total_cost)->toBe(0)->and($this->game->fresh()->total_tips)->toBe(20)
        ->and($this->game->fresh()->cost_recorded_by)->toBe($this->admin->id)
        ->and($other->fresh()->total_cost)->toBeNull()->and($other->fresh()->total_tips)->toBeNull();
});

test('recording totals on a legacy closed game rejects negative income without partial totals', function () {
    $this->game->update(['status' => 'closed']);
    expect(fn () => app(RecordGameTotals::class)->record($this->game, $this->admin, 200, 101))
        ->toThrow(ValidationException::class);
    expect($this->game->fresh()->total_tips)->toBeNull()->and($this->game->fresh()->total_cost)->toBeNull()
        ->and($this->game->fresh()->tips_recorded_by)->toBeNull();
});

test('closing retains the applied list context and defaults both totals to zero', function () {
    $filters = ['from_date' => '2026-10-01', 'status' => 'open', 'page' => 2];
    $this->actingAs($this->admin)->post(route('games.close', ['gameSession' => $this->game->id, ...$filters]))
        ->assertRedirect(route('games.show', ['gameSession' => $this->game->id, ...$filters]))->assertSessionHasNoErrors();
    expect($this->game->fresh()->total_tips)->toBe(0)->and($this->game->fresh()->total_cost)->toBe(0);
});
