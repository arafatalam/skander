<?php

use App\Actions\Games\CalculateGameBalance;
use App\Models\BuyIn;
use App\Models\Cashout;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

uses(TestCase::class);

test('calculates the balance from this game across all visits', function (
    int $opening,
    array $buyIns,
    array $cashouts,
    int $expected,
) {
    $participant = new PlayerGame(['opening_balance' => $opening]);
    $player = new Player;
    $player->balance = 99999;
    $participant->setRelation('player', $player);
    $participant->setRelation('buyIns', new Collection(array_map(
        fn (array $buyIn) => new BuyIn(['type' => $buyIn[0], 'amount' => $buyIn[1]]),
        $buyIns,
    )));
    $participant->setRelation('visits', new Collection(array_map(
        function (?array $cashout) {
            $visit = new PlayerGameVisit;
            $visit->setRelation('cashout', $cashout === null ? null : new Cashout([
                'ending_chips' => $cashout[0],
                'cash_paid' => $cashout[1],
            ]));

            return $visit;
        },
        $cashouts,
    )));

    expect((new CalculateGameBalance)->calculate($participant))->toBe($expected)
        ->and($participant->opening_balance)->toBe($opening)
        ->and($player->balance)->toBe(99999);
})->with([
    'first confirmed example' => [-200, [['cash', 500], ['credit', 500]], [[2000, 1000]], 300],
    'second confirmed example' => [200, [['credit', 1000], ['cash', 500]], [[700, 0]], -100],
    'repeated visits and mixed return' => [0, [['cash', 500], ['cash', 200], ['credit', 300]], [[500, 200], [400, 100]], 300],
    'opening counted once across visits' => [200, [['cash', 500], ['credit', 300]], [[500, 200], [400, 100]], 500],
    'open visit without cashout' => [200, [['credit', 300]], [null], -100],
    'cash buy-in preserves opening' => [-200, [['cash', 500]], [null], -200],
    'zero balance' => [0, [['credit', 300]], [[500, 200]], 0],
    'no activity' => [200, [], [], 200],
]);
