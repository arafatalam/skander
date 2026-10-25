<?php

use App\Actions\Corrections\GetCorrectionHistory;
use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Players\CalculatePlayerBalance;
use App\Models\BuyIn;
use App\Models\BuyInCorrection;
use App\Models\Cashout;
use App\Models\CashoutCorrection;
use App\Models\GameSession;
use App\Models\GameTotalCorrection;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->player = Player::create(['name' => 'Audit Player']);
    $this->game = GameSession::create([
        'game_date' => '2026-10-24', 'game_number' => 1, 'game_name' => '24_OCT_2026_1',
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $this->admin->id,
        'total_tips' => 50, 'tips_recorded_by' => $this->admin->id, 'tips_recorded_at' => now(),
    ]);
    $this->participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 100, 0);
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $this->cashout = Cashout::firstOrFail();
    $this->buyIn = BuyIn::firstOrFail();
    $this->payment = Payment::create([
        'player_id' => $this->player->id, 'submission_id' => (string) Str::uuid(),
        'amount' => 50, 'direction' => 'house_pays_player', 'paid_at' => '2026-10-24', 'created_by' => $this->admin->id,
    ]);
    $this->cashoutRevision = fn (array $attributes = []) => CashoutCorrection::create(array_merge([
        'cashout_id' => $this->cashout->id, 'revision' => 1, 'submission_id' => (string) Str::uuid(),
        'previous_ending_chips' => 0, 'previous_cash_paid' => 0, 'new_ending_chips' => 500, 'new_cash_paid' => 300,
        'reason' => 'Forgotten settlement', 'corrected_by' => $this->admin->id, 'corrected_at' => now(),
    ], $attributes));
});

test('every history route is admin only and read only', function () {
    $urls = [
        route('buy-ins.corrections', $this->buyIn), route('cashouts.corrections', $this->cashout),
        route('payments.corrections', $this->payment),
        route('games.corrections', [$this->game, 'total_tips']), route('games.corrections', [$this->game, 'total_cost']),
    ];
    foreach ($urls as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    $this->actingAs(User::factory()->create(['role' => 'user']));
    foreach ($urls as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->actingAs($this->admin);
    foreach ($urls as $url) {
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('corrections/Show')->has('revisions', 0));
        $this->post($url)->assertStatus(405);
        $this->patch($url)->assertStatus(405);
        $this->delete($url)->assertStatus(405);
    }
    $this->get('/games/'.$this->game->id.'/corrections/balance')->assertNotFound();
    $this->get('/cashouts/999999/corrections')->assertNotFound();
});

test('two revisions preserve original automatic cashout and count only current values', function () {
    ($this->cashoutRevision)();
    ($this->cashoutRevision)([
        'revision' => 2, 'previous_ending_chips' => 500, 'previous_cash_paid' => 300,
        'new_ending_chips' => 600, 'new_cash_paid' => 300, 'reason' => 'Correct final chip count',
    ]);
    // Effective values are fixtures here. PG-027 will save revisions and rebuild balances atomically.
    $this->cashout->update(['ending_chips' => 600, 'cash_paid' => 300]);
    $history = app(GetCorrectionHistory::class)->get($this->cashout->fresh());
    expect($history['source'])->toBe('automatic')
        ->and($history['record_id'])->toBe($this->cashout->id)
        ->and($history['original']['values'][0]['value'])->toBe('$0')
        ->and($history['original']['actor'])->toBe($this->admin->name)
        ->and($history['current'][0]['value'])->toBe('$600')
        ->and($history['revisions'][0]['after'][0]['value'])->toBe('$500')
        ->and($history['revisions'][1]['before'][0]['value'])->toBe('$500')
        ->and($history['revisions'][1]['reason'])->toBe('Correct final chip count')
        ->and($history['revisions'][1]['actor'])->toBe($this->admin->name)
        ->and($this->game->fresh()->status)->toBe('closed')
        ->and(app(CalculatePlayerBalance::class)->calculate($this->player, '2026-10-24'))->toBe(250);
    $this->actingAs($this->admin)->get(route('cashouts.corrections', $this->cashout))->assertInertia(fn (Assert $page) => $page
        ->where('source', 'automatic')->has('revisions', 2)->where('revisions.1.revision', 2));
});

test('buy-in payment and game total histories reconstruct all supported fields', function () {
    $audit = ['revision' => 1, 'reason' => 'Entry corrected', 'corrected_by' => $this->admin->id, 'corrected_at' => now()];
    BuyInCorrection::create($audit + [
        'buy_in_id' => $this->buyIn->id, 'submission_id' => (string) Str::uuid(),
        'previous_amount' => 100, 'new_amount' => 200, 'previous_type' => 'cash', 'new_type' => 'credit',
    ]);
    $this->buyIn->update(['amount' => 200, 'type' => 'credit']);
    $buyIn = app(GetCorrectionHistory::class)->get($this->buyIn->fresh());
    expect($buyIn['original']['values'][1]['value'])->toBe('Cash')->and($buyIn['current'][1]['value'])->toBe('Credit');
    PaymentCorrection::create($audit + [
        'payment_id' => $this->payment->id, 'submission_id' => (string) Str::uuid(),
        'previous_amount' => 50, 'new_amount' => 100,
        'previous_direction' => 'house_pays_player', 'new_direction' => 'house_collects_from_player',
        'previous_paid_at' => '2026-10-23', 'new_paid_at' => '2026-10-24',
        'previous_note' => null, 'new_note' => 'cash',
    ]);
    $this->payment->update(['amount' => 100, 'direction' => 'house_collects_from_player', 'note' => 'cash']);
    $payment = app(GetCorrectionHistory::class)->get($this->payment->fresh());
    expect($payment['original']['values'][2]['value'])->toBe('2026-10-23')
        ->and($payment['original']['values'][3]['value'])->toBe('Not recorded')
        ->and($payment['revisions'][0]['before'][3]['value'])->toBe('Not recorded')
        ->and($payment['current'][3]['value'])->toBe('cash')
        ->and($payment['revisions'][0]['after'][1]['value'])->toBe('House collects from player');
    GameTotalCorrection::create($audit + [
        'game_session_id' => $this->game->id, 'field' => 'total_tips',
        'submission_id' => (string) Str::uuid(), 'previous_amount' => 25, 'new_amount' => 50,
    ]);
    $tips = app(GetCorrectionHistory::class)->get($this->game->fresh(), 'total_tips');
    expect($tips['original']['values'][0]['value'])->toBe('$25')->and($tips['current'][0]['value'])->toBe('$50');
    $cost = app(GetCorrectionHistory::class)->get($this->game->fresh(), 'total_cost');
    expect($cost['revisions'])->toBe([])->and($cost['current'][0]['value'])->toBe('Not recorded');
});

test('corrections require a reason and cannot be changed or deleted through models', function () {
    expect(fn () => ($this->cashoutRevision)(['reason' => '   ']))->toThrow(LogicException::class);
    $correction = ($this->cashoutRevision)();
    expect(fn () => $correction->update(['reason' => 'Rewritten']))->toThrow(LogicException::class);
    expect(fn () => $correction->fresh()->delete())->toThrow(LogicException::class);
    expect($correction->fresh()->reason)->toBe('Forgotten settlement');
});

test('database rejects duplicate revision and submission identity', function (string $duplicate) {
    $first = ($this->cashoutRevision)();
    $attributes = $duplicate === 'revision' ? [] : ['revision' => 2, 'submission_id' => $first->submission_id];
    expect(fn () => ($this->cashoutRevision)($attributes))->toThrow(QueryException::class);
    $this->assertDatabaseCount('cashout_corrections', 1);
})->with(['revision', 'submission']);

test('audit foreign keys prevent removal of the original event', function () {
    ($this->cashoutRevision)();
    expect(fn () => DB::table('cashouts')->where('id', $this->cashout->id)->delete())->toThrow(QueryException::class);
});

test('existing game total corrections receive ordered identities without changing their history', function () {
    $migration = require database_path('migrations/2026_10_24_140003_add_revision_identity_to_game_total_corrections.php');
    $migration->down();
    $row = [
        'game_session_id' => $this->game->id, 'field' => 'total_tips', 'previous_amount' => 10,
        'new_amount' => 20, 'reason' => 'Existing correction', 'corrected_by' => $this->admin->id,
        'corrected_at' => '2026-10-24 12:00:00', 'created_at' => '2026-10-24 12:00:00', 'updated_at' => '2026-10-24 12:00:00',
    ];
    DB::table('game_total_corrections')->insert($row);
    DB::table('game_total_corrections')->insert(array_merge($row, ['previous_amount' => 20, 'new_amount' => 50]));
    DB::table('game_total_corrections')->insert(array_merge($row, ['field' => 'total_cost']));
    $before = DB::table('game_total_corrections')->orderBy('id')->get();
    $migration->up();
    $after = DB::table('game_total_corrections')->orderBy('id')->get();
    expect($after->pluck('revision')->all())->toBe([1, 2, 1])
        ->and($after->pluck('submission_id')->unique()->count())->toBe(3);
    foreach ($before as $index => $original) {
        foreach ((array) $original as $field => $value) {
            expect($after[$index]->{$field})->toBe($value);
        }
    }
});
