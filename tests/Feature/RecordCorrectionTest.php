<?php

use App\Actions\Corrections\CheckHistoricalBuyIns;
use App\Actions\Corrections\GetCorrectionWorkspace;
use App\Actions\Corrections\RecordCorrection;
use App\Actions\Corrections\ValidateCorrectionInput;
use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Players\CalculatePlayerBalance;
use App\Models\Cashout;
use App\Models\CorrectionSubmission;
use App\Models\GameSession;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->player = Player::create(['name' => 'Correction player']);
    $this->game = GameSession::create([
        'game_date' => '2026-10-20', 'game_number' => 1, 'game_name' => '20_OCT_2026_1',
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $this->admin->id,
    ]);
    $this->participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 200, 100);
    $this->visit = $this->participant->visits()->firstOrFail();
    $this->buyIn = $this->participant->buyIns()->orderBy('id')->firstOrFail();
    $this->data = [
        'kind' => 'buy_in', 'game_id' => $this->game->id, 'player_id' => $this->player->id,
        'target_id' => $this->buyIn->id, 'cash_amount' => 0, 'credit_amount' => 500, 'revision' => 0,
        'submission_id' => (string) Str::uuid(), 'reason' => 'Checked original records',
    ];
    $this->record = app(RecordCorrection::class);
});

test('workspace requires admin access for reading and writing', function () {
    $this->get(route('record-correction.index'))->assertRedirect(route('login'));
    $this->post(route('record-correction.store'), $this->data)->assertRedirect(route('login'));
    $other = User::factory()->create(['role' => 'player']);
    $this->actingAs($other)->get(route('record-correction.index'))->assertForbidden();
    $this->post(route('record-correction.store'), $this->data)->assertForbidden();
    expect(fn () => $this->record->record($other, $this->data))->toThrow(AuthorizationException::class);
    expect(CorrectionSubmission::count())->toBe(0);
});

test('groups mixed purchases and filters relevant inactive players for each tab', function () {
    $this->player->is_active = false;
    $this->player->save();
    $this->actingAs($this->admin)->get(route('record-correction.index', [
        'tab' => 'buy-ins', 'game_id' => $this->game->id, 'player_id' => $this->player->id,
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('corrections/Record')
        ->has('players', 1)->where('players.0.is_active', false)->has('records', 1)
        ->where('records.0.inputs.cash_amount', 200)->where('records.0.inputs.credit_amount', 100));
    $workspace = app(GetCorrectionWorkspace::class);
    expect($workspace->get(['tab' => 'cashouts', 'game_id' => $this->game->id])['players'])->toHaveCount(0);
    expect($workspace->get(['tab' => 'payments'])['players'])->toHaveCount(0);
    app(CloseGameSession::class)->close($this->game, $this->admin);
    expect($workspace->get(['tab' => 'cashouts', 'game_id' => $this->game->id])['players'])->toHaveCount(1);
});

test('group corrections preserve both original portions update balances and reject stale or changed retries', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $entry = $this->record->record($this->admin, $this->data);
    expect($this->player->fresh()->balance)->toBe(-500)
        ->and($this->game->fresh()->status)->toBe('closed')
        ->and($this->participant->buyIns()->where('type', 'cash')->first()->amount)->toBe(0)
        ->and($this->participant->buyIns()->where('type', 'credit')->first()->amount)->toBe(500)
        ->and($this->buyIn->corrections()->first()->previous_amount)->toBe(200)
        ->and($this->record->record($this->admin, $this->data)->id)->toBe($entry->id)
        ->and(CorrectionSubmission::count())->toBe(1);
    expect(fn () => $this->record->record($this->admin, array_replace($this->data, ['credit_amount' => 600])))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->record->record($this->admin, array_replace($this->data, ['submission_id' => (string) Str::uuid()])))
        ->toThrow(ValidationException::class);
    $balances = app(CalculatePlayerBalance::class);
    expect($balances->calculate($this->player, '2026-10-19'))->toBe(0)
        ->and($balances->calculate($this->player, '2026-10-20'))->toBe(-500)
        ->and($balances->calculate($this->player, '2026-10-21'))->toBe(-500);
});

test('adds a previously absent cash or credit portion and preserves its original zero in history', function () {
    $newPlayer = Player::create(['name' => 'Cash only']);
    $participant = app(CreateBuyIn::class)->create($this->game, $newPlayer, $this->admin, 300, 0);
    $row = $participant->buyIns()->firstOrFail();
    $this->record->record($this->admin, array_replace($this->data, [
        'player_id' => $newPlayer->id, 'target_id' => $row->id, 'credit_amount' => 50,
    ]));
    $credit = $participant->buyIns()->where('type', 'credit')->firstOrFail();
    expect($credit->amount)->toBe(50)->and($credit->corrections()->first()->previous_amount)->toBe(0)
        ->and(CorrectionSubmission::latest('id')->first()->warnings)->not->toBeEmpty();
});

test('corrects tips and costs separately including missing totals without affecting balances', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    foreach (['total_tips', 'total_cost'] as $kind) {
        $this->record->record($this->admin, array_replace($this->data, ['kind' => $kind, 'amount' => 75, 'submission_id' => (string) Str::uuid()]));
    }
    expect($this->game->fresh()->total_tips)->toBe(75)->and($this->game->fresh()->total_cost)->toBe(75)
        ->and($this->player->fresh()->balance)->toBe(-100)
        ->and($this->game->totalCorrections()->count())->toBe(2)
        ->and($this->game->totalCorrections()->first()->previous_amount)->toBeNull();
});

test('missing payment accepts historical direction and date and shows in inline addition history', function () {
    $input = ['kind' => 'missing_payment', 'player_id' => $this->player->id, 'amount' => 200,
        'direction' => 'house_pays_player', 'paid_at' => '2026-10-19', 'note' => null,
        'reason' => 'Forgot to record past cash transfer', 'submission_id' => (string) Str::uuid()];
    $entry = $this->record->record($this->admin, $input);
    expect($this->player->fresh()->balance)->toBe(-300)
        ->and(app(CalculatePlayerBalance::class)->calculate($this->player, '2026-10-19'))->toBe(-200)
        ->and($this->record->record($this->admin, $input)->id)->toBe($entry->id)->and(Payment::count())->toBe(1);
    $view = app(GetCorrectionWorkspace::class)->get(['tab' => 'payments', 'player_id' => $this->player->id]);
    expect($view['records'][0]['additions'])->toHaveCount(1);
    $payment = Payment::firstOrFail();
    $this->record->record($this->admin, array_replace($input, ['kind' => 'payment', 'target_id' => $payment->id,
        'revision' => 0, 'paid_at' => '2026-10-21', 'submission_id' => (string) Str::uuid()]));
    expect(app(CalculatePlayerBalance::class)->calculate($this->player, '2026-10-19'))->toBe(0)
        ->and($this->player->fresh()->balance)->toBe(-300);
});

test('adds a forgotten player with multiple completed visits and separate purchases atomically', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $forgotten = Player::create(['name' => 'Forgotten', 'is_active' => false]);
    $input = [
        'kind' => 'missing_player', 'game_id' => $this->game->id, 'player_id' => $forgotten->id,
        'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion(null),
        'reason' => 'Entire player omitted', 'submission_id' => (string) Str::uuid(),
        'visits' => [
            ['buy_ins' => [['cash_amount' => 200, 'credit_amount' => 100], ['cash_amount' => 0, 'credit_amount' => 50]], 'ending_chips' => 500, 'cash_paid' => 300],
            ['buy_ins' => [['cash_amount' => 200, 'credit_amount' => 300]], 'ending_chips' => 200, 'cash_paid' => 200],
        ],
    ];
    $this->actingAs($this->admin)->post(route('record-correction.store'), $input)
        ->assertSessionHasNoErrors()->assertRedirect(route('record-correction.index', [
            'tab' => 'missing', 'game_id' => $this->game->id, 'player_id' => $forgotten->id, 'mode' => 'missing_visit',
        ]))->assertSessionHas('correction_result.warnings');
    $participant = PlayerGame::where('player_id', $forgotten->id)->firstOrFail();
    expect($participant->visits()->count())->toBe(2)->and($participant->buyIns()->count())->toBe(5)
        ->and($forgotten->fresh()->balance)->toBe(-250)->and($this->game->fresh()->status)->toBe('closed');
    $entry = CorrectionSubmission::firstOrFail();
    expect($entry->warnings)->toHaveCount(1)->and($entry->reason)->toContain("\n\nEntire player omitted");
    $this->record->record($this->admin, $input);
    expect(PlayerGame::where('player_id', $forgotten->id)->count())->toBe(1)->and(CorrectionSubmission::count())->toBe(1);
});

test('missing buy-in keeps existing cashout and detects changed visit sequence', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail()->toArray();
    $input = array_replace($this->data, ['kind' => 'missing_buy_in', 'visit_id' => $this->visit->id,
        'cash_amount' => 0, 'credit_amount' => 50, 'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion($this->participant)]);
    $entry = $this->record->record($this->admin, $input);
    expect($this->visit->cashout()->first()->toArray())->toBe($cashout)
        ->and($this->player->fresh()->balance)->toBe(-150)->and($entry->warnings)->not->toBeEmpty();
    expect(fn () => $this->record->record($this->admin, array_replace($input, ['submission_id' => (string) Str::uuid()])))
        ->toThrow(ValidationException::class);
});

test('inserts a forgotten visit before an existing return without changing its money records', function () {
    app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 300, 300, $this->visit->id);
    app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 300, 0);
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $existingVisit = $this->participant->visits()->where('visit_number', 2)->firstOrFail();
    $before = $existingVisit->buyIns()->get()->toArray();
    $input = array_replace($this->data, ['kind' => 'missing_visit', 'position' => 2,
        'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion($this->participant),
        'visits' => [['buy_ins' => [['cash_amount' => 300, 'credit_amount' => 0]], 'ending_chips' => 500, 'cash_paid' => 300]],
    ]);
    $entry = $this->record->record($this->admin, $input);
    expect($existingVisit->fresh()->visit_number)->toBe(3)
        ->and($existingVisit->buyIns()->get()->toArray())->toBe($before)
        ->and($entry->warnings)->toHaveCount(1)
        ->and($entry->warnings[0])->toContain('Visit 3')->toContain('$300')
        ->and($this->participant->visits()->count())->toBe(3)
        ->and($this->game->fresh()->status)->toBe('closed');
});

test('missing cashout rejects automatic duplicate and allows correcting that original instead', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $input = array_replace($this->data, ['kind' => 'missing_cashout', 'visit_id' => $this->visit->id,
        'ending_chips' => 500, 'cash_paid' => 300, 'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion($this->participant)]);
    expect(fn () => $this->record->record($this->admin, $input))->toThrow(ValidationException::class);
    $this->record->record($this->admin, array_replace($input, ['kind' => 'cashout', 'target_id' => $cashout->id]));
    expect(Cashout::count())->toBe(1)->and($cashout->fresh()->creation_method)->toBe('automatic')
        ->and($this->player->fresh()->balance)->toBe(100);
});

test('invalid nested historical inputs and forged ownership do not write records', function () {
    $other = Player::create(['name' => 'Different player']);
    $this->actingAs($this->admin)->post(route('record-correction.store'), array_replace($this->data, ['player_id' => $other->id]))->assertNotFound();
    $input = ['kind' => 'missing_player', 'game_id' => $this->game->id, 'player_id' => $other->id,
        'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion(null), 'submission_id' => (string) Str::uuid(), 'reason' => 'Missing player',
        'visits' => [['buy_ins' => [['cash_amount' => 1.5, 'credit_amount' => 0]], 'ending_chips' => 0, 'cash_paid' => 0]]];
    $this->post(route('record-correction.store'), $input)->assertSessionHasErrors('visits.0.buy_ins.0.cash_amount');
    expect(CorrectionSubmission::count())->toBe(0)->and(PlayerGame::where('player_id', $other->id)->count())->toBe(0);
});

test('balance failure rolls back grouped corrections and audit submissions', function () {
    $mock = Mockery::mock(CalculatePlayerBalance::class);
    $mock->shouldReceive('calculate')->once()->andThrow(new RuntimeException('Balance failure'));
    $action = new RecordCorrection(app(ValidateCorrectionInput::class), $mock);
    expect(fn () => $action->record($this->admin, $this->data))->toThrow(RuntimeException::class, 'Balance failure');
    expect($this->buyIn->fresh()->amount)->toBe(200)->and($this->buyIn->corrections()->count())->toBe(0)
        ->and(CorrectionSubmission::count())->toBe(0)->and($this->player->fresh()->balance)->toBe(-100);
});

test('historical visits require explicit cashout fields and roll back incomplete additions', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $other = Player::create(['name' => 'Forgotten player']);
    $input = ['kind' => 'missing_player', 'game_id' => $this->game->id, 'player_id' => $other->id,
        'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion(null),
        'submission_id' => (string) Str::uuid(), 'reason' => 'Missing player',
        'visits' => [['buy_ins' => [['cash_amount' => 200, 'credit_amount' => 100]], 'ending_chips' => 500]],
    ];
    $this->actingAs($this->admin)->post(route('record-correction.store'), $input)
        ->assertSessionHasErrors('visits.0.cash_paid');
    $input['visits'][0]['cash_paid'] = 0;
    $input['visits'][] = ['buy_ins' => [['cash_amount' => 0, 'credit_amount' => 0]], 'ending_chips' => 0, 'cash_paid' => 0];
    $this->post(route('record-correction.store'), $input)->assertSessionHasErrors('buy_ins');
    expect(PlayerGame::where('player_id', $other->id)->count())->toBe(0)
        ->and(CorrectionSubmission::count())->toBe(0)
        ->and($this->game->fresh()->status)->toBe('closed');
});

test('adds a genuinely missing cashout without closing the game or creating a duplicate', function () {
    $input = array_replace($this->data, ['kind' => 'missing_cashout', 'visit_id' => $this->visit->id,
        'ending_chips' => 500, 'cash_paid' => 0,
        'sequence_version' => app(CheckHistoricalBuyIns::class)->sequenceVersion($this->participant)]);
    $entry = $this->record->record($this->admin, $input);
    $this->record->record($this->admin, $input);
    expect(Cashout::count())->toBe(1)->and($this->player->fresh()->balance)->toBe(400)
        ->and($this->game->fresh()->status)->toBe('open');
    $view = app(GetCorrectionWorkspace::class)->get(['tab' => 'cashouts', 'game_id' => $this->game->id, 'player_id' => $this->player->id]);
    expect($view['records'][0]['additions'][0]['id'])->toBe($entry->id);
    expect(fn () => $entry->update(['reason' => 'Erase audit']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});

test('normal live buy ins still enforce limits after historical correction exceptions', function () {
    $this->record->record($this->admin, array_replace($this->data, ['cash_amount' => 50, 'credit_amount' => 0]));
    expect(fn () => app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 50, 0))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 501, 0))
        ->toThrow(ValidationException::class);
    expect($this->participant->buyIns()->sum('amount'))->toBe(50);
});
