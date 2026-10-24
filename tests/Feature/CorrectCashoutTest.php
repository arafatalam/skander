<?php

use App\Actions\Corrections\GetCorrectionHistory;
use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CorrectCashout;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Players\CalculatePlayerBalance;
use App\Models\CashoutCorrection;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->player = Player::create(['name' => 'Cashout correction test']);
    $this->game = GameSession::create([
        'game_date' => '2026-10-23', 'game_number' => 1, 'game_name' => '23_OCT_2026_1',
        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500, 'created_by' => $this->admin->id,
    ]);
    $this->participant = app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 100, 0);
    $this->visit = $this->participant->visits()->firstOrFail();
    $this->input = [
        'ending_chips' => 500, 'cash_paid' => 300, 'reason' => 'Correct forgotten cashout',
        'revision' => 0, 'submission_id' => (string) Str::uuid(),
    ];
    $this->action = app(CorrectCashout::class);
});

test('corrects automatic cashouts with full partial and no payment and retains provenance', function (int $cashPaid, int $expected) {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $recordedAt = $cashout->cashed_out_at->toIso8601String();
    $correction = $this->action->correct($cashout, $this->admin, array_replace($this->input, ['cash_paid' => $cashPaid]));

    expect($this->player->fresh()->balance)->toBe($expected)
        ->and($this->game->fresh()->status)->toBe('closed')
        ->and($cashout->fresh()->creation_method)->toBe('automatic')
        ->and($cashout->fresh()->cashed_out_at->toIso8601String())->toBe($recordedAt)
        ->and($cashout->fresh()->created_by)->toBe($this->admin->id)
        ->and($correction->previous_ending_chips)->toBe(0)
        ->and($correction->previous_cash_paid)->toBe(0)
        ->and($correction->new_ending_chips)->toBe(500)
        ->and($correction->new_cash_paid)->toBe($cashPaid)
        ->and($correction->corrected_by)->toBe($this->admin->id)
        ->and($correction->corrected_at)->not->toBeNull()
        ->and($correction->reason)->toBe($this->input['reason']);
    $balances = app(CalculatePlayerBalance::class);
    expect($balances->calculate($this->player, '2026-10-22'))->toBe(0)
        ->and($balances->calculate($this->player, '2026-10-23'))->toBe($expected)
        ->and($balances->calculate($this->player, '2026-10-24'))->toBe($expected);
    $history = app(GetCorrectionHistory::class)->get($cashout->fresh());
    expect($history['original']['values'][0]['value'])->toBe('$0')
        ->and($history['current'][0]['value'])->toBe('$500')
        ->and($history['revisions'])->toHaveCount(1);
})->with(['full payment' => [500, 0], 'partial payment' => [300, 200], 'no payment' => [0, 500]]);

test('replaces a nonzero contribution and preserves successive corrections without duplicate retries', function () {
    $cashout = app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 300, 100, $this->visit->id);
    $first = $this->action->correct($cashout, $this->admin, $this->input);
    expect($this->player->fresh()->balance)->toBe(200);
    $secondInput = array_replace($this->input, [
        'cash_paid' => 0, 'revision' => 1, 'submission_id' => (string) Str::uuid(),
    ]);
    $second = $this->action->correct($cashout, $this->admin, $secondInput);
    $retry = $this->action->correct($cashout, $this->admin, $this->input);
    expect($retry->id)->toBe($first->id)
        ->and(CashoutCorrection::count())->toBe(2)
        ->and($this->player->fresh()->balance)->toBe(500)
        ->and($first->previous_ending_chips)->toBe(300)
        ->and($first->previous_cash_paid)->toBe(100)
        ->and($second->previous_ending_chips)->toBe(500)
        ->and($second->previous_cash_paid)->toBe(300)
        ->and($this->game->fresh()->status)->toBe('open');
    expect(fn () => $this->action->correct($cashout, $this->admin, array_replace($this->input, [
        'submission_id' => (string) Str::uuid(),
    ])))->toThrow(ValidationException::class);
    expect(fn () => $this->action->correct($cashout, $this->admin, array_replace($this->input, [
        'reason' => 'Different request',
    ])))->toThrow(ValidationException::class);
    expect(CashoutCorrection::count())->toBe(2);
});

test('preserves a mixed return and topups and prepends a mismatch warning to the required explanation', function (int $chips, bool $warn) {
    $cashout = app(CreateCashout::class)->create($this->game, $this->player, $this->admin, 300, 300, $this->visit->id);
    app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 100, 200);
    app(CreateBuyIn::class)->create($this->game, $this->player, $this->admin, 200, 0);
    $before = $this->participant->buyIns()->orderBy('id')->get()->toArray();
    $input = array_replace($this->input, ['ending_chips' => $chips, 'reason' => "Chip count checked.\n\nAdmin explanation."]);
    $correction = $this->action->correct($cashout, $this->admin, $input);
    expect($this->participant->buyIns()->orderBy('id')->get()->toArray())->toBe($before)
        ->and($this->player->fresh()->balance)->toBe($chips - 300 - 200)
        ->and($this->action->correct($cashout, $this->admin, $input)->id)->toBe($correction->id)
        ->and(CashoutCorrection::count())->toBe(1);
    if ($warn) {
        expect($correction->reason)->toStartWith('Warning: Corrected ending chips $')
            ->toContain('The recorded $300 return buy-in is unchanged.')
            ->toEndWith("\n\n".$input['reason']);
        if ($chips > 500) {
            expect($correction->reason)->toContain('exactly $2,000');
        }
    } else {
        expect($correction->reason)->toBe($input['reason']);
    }
})->with(['below minimum' => [500, true], 'above maximum exception' => [2000, true], 'valid return' => [50, false]]);

test('rejects invalid correction input without changing any money records', function (string $field, mixed $value) {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    expect(fn () => $this->action->correct($cashout, $this->admin, array_replace($this->input, [$field => $value])))
        ->toThrow(ValidationException::class);
    expect(CashoutCorrection::count())->toBe(0)
        ->and($cashout->fresh()->ending_chips)->toBe(0)
        ->and($this->player->fresh()->balance)->toBe(0);
})->with([
    ['ending_chips', -1], ['cash_paid', -1], ['ending_chips', 1.5], ['cash_paid', 1.5],
    ['ending_chips', 2147483648], ['cash_paid', 2147483648],
    ['reason', '   '], ['reason', null], ['submission_id', 'invalid'], ['revision', -1],
]);

test('requires admin authorization even when the action is called directly', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $user = User::factory()->create(['role' => 'player']);
    expect(fn () => $this->action->correct($cashout, $user, $this->input))->toThrow(AuthorizationException::class);
    expect(CashoutCorrection::count())->toBe(0)->and($cashout->fresh()->ending_chips)->toBe(0);
});

test('rolls back history and effective cashout when balance recalculation fails', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $balances = Mockery::mock(CalculatePlayerBalance::class);
    $balances->shouldReceive('calculate')->once()->andThrow(new RuntimeException('Balance failure'));
    expect(fn () => (new CorrectCashout($balances))->correct($cashout, $this->admin, $this->input))
        ->toThrow(RuntimeException::class, 'Balance failure');
    expect(CashoutCorrection::count())->toBe(0)
        ->and($cashout->fresh()->ending_chips)->toBe(0)
        ->and($cashout->fresh()->cash_paid)->toBe(0)
        ->and($this->player->fresh()->balance)->toBe(0)
        ->and($this->game->fresh()->status)->toBe('closed');
});

test('admin can load correction props and submit a closed game cashout correction', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $this->actingAs($this->admin)->get(route('cashouts.edit', $cashout))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
            // The Vue form is the next implementation step.
        ->component('cashouts/Edit', false)
        ->where('cashout.id', $cashout->id)
        ->where('cashout.ending_chips', 0)
        ->where('cashout.cash_paid', 0)
        ->where('cashout.revision', 0)
        ->where('cashout.player_name', $this->player->name)
        ->where('cashout.game_name', $this->game->game_name)
        ->where('submissionId', fn ($id) => Str::isUuid($id))
        ->where('updateUrl', route('cashouts.update', $cashout))
        ->where('backUrl', route('player-games.show', $this->participant)));

    $this->patch(route('cashouts.update', $cashout), array_replace($this->input, ['reason' => ' ']))
        ->assertSessionHasErrors('reason');
    expect(CashoutCorrection::count())->toBe(0);

    $this->patch(route('cashouts.update', $cashout), $this->input)
        ->assertSessionHasNoErrors()->assertRedirect(route('cashouts.corrections', $cashout));
    expect($this->player->fresh()->balance)->toBe(200)
        ->and($this->game->fresh()->status)->toBe('closed')
        ->and(CashoutCorrection::count())->toBe(1);
});

test('correction form and submission routes reject guests and non admins', function () {
    app(CloseGameSession::class)->close($this->game, $this->admin);
    $cashout = $this->visit->cashout()->firstOrFail();
    $this->get(route('cashouts.edit', $cashout))->assertRedirect(route('login'));
    $this->patch(route('cashouts.update', $cashout), $this->input)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'player']));
    $this->get(route('cashouts.edit', $cashout))->assertForbidden();
    $this->patch(route('cashouts.update', $cashout), $this->input)->assertForbidden();
    expect(CashoutCorrection::count())->toBe(0)->and($this->player->fresh()->balance)->toBe(0);
});
