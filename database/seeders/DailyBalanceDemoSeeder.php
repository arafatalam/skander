<?php

namespace Database\Seeders;

use App\Actions\Games\CloseGameSession;
use App\Actions\Games\CreateBuyIn;
use App\Actions\Games\CreateCashout;
use App\Actions\Payments\CorrectPayment;
use App\Actions\Payments\RecordPayment;
use App\Models\GameSession;
use App\Models\GameSetting;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

class DailyBalanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (GameSession::exists()) {
            throw new LogicException('Demo data requires an empty game history.');
        }
        $admin = User::where('role', 'admin')->firstOrFail();
        $players = Player::where('is_active', true)->orderBy('id')->limit(3)->get();
        if ($players->isEmpty()) {
            throw new LogicException('At least one existing active player is required.');
        }
        GameSetting::updateOrCreate(['id' => 1], ['default_min_buy_in' => 100, 'default_max_buy_in' => 500]);
        $today = Carbon::today();
        $originalClock = Carbon::getTestNow();
        try {
            for ($day = 5; $day >= 0; $day--) {
                $date = $today->copy()->subDays($day);
                Carbon::setTestNow($date->copy()->setTime(19, 0));
                $gameCount = $day === 2 ? 2 : 1;
                for ($number = 1; $number <= $gameCount; $number++) {
                    $game = GameSession::create([
                        'game_date' => $date->toDateString(), 'game_number' => $number,
                        'game_name' => strtoupper($date->format('d_M_Y')).'_'.$number,
                        'status' => 'open', 'min_buy_in' => 100, 'max_buy_in' => 500,
                        'created_by' => $admin->id,
                    ]);
                    foreach ($players as $index => $player) {
                        $credit = $day === 5 || $day === 0 ? 200 : 0;
                        $participant = app(CreateBuyIn::class)->create($game, $player, $admin, $credit === 0 ? 100 : 0, $credit);
                        if ($day === 0) {
                            continue; // One open game for testing additional activity.
                        }
                        if ($day === 5 && $index === 0) {
                            continue; // Automatic zero at closure.
                        }
                        $chips = $day === 5 ? 400 : 100;
                        $paid = $day === 5 ? 50 : 100;
                        app(CreateCashout::class)->create($game, $player, $admin, $chips, $paid, $participant->latestVisit->id);
                        if ($day === 3 && $index === 0) {
                            $participant = app(CreateBuyIn::class)->create($game, $player, $admin, 100, 0);
                            app(CreateCashout::class)->create($game, $player, $admin, 150, 100, $participant->latestVisit->id);
                        }
                    }
                    if ($day !== 0) {
                        $game->update([
                            'total_tips' => 25, 'total_cost' => 40,
                            'tips_recorded_by' => $admin->id, 'cost_recorded_by' => $admin->id,
                            'tips_recorded_at' => now(), 'cost_recorded_at' => now(),
                        ]);
                        app(CloseGameSession::class)->close($game, $admin);
                    }
                }
            }
            Carbon::setTestNow($today->copy()->setTime(21, 0));
            foreach ($players as $index => $player) {
                $balance = $player->fresh()->balance;
                if ($balance === 0) {
                    continue;
                }
                $direction = $balance > 0 ? 'house_pays_player' : 'house_collects_from_player';
                $payment = app(RecordPayment::class)->record($player, $admin, 50, $direction,
                    $today->copy()->subDays(2)->toDateString(), (string) Str::uuid(), 'Sample backdated payment');
                if ($index === 0) {
                    app(CorrectPayment::class)->correct($payment, $admin, [
                        'amount' => 50, 'direction' => $direction,
                        'paid_at' => $today->copy()->subDay()->toDateString(),
                        'note' => 'Sample corrected payment date', 'reason' => 'Sample: payment was made one day later.',
                        'revision' => 0, 'submission_id' => (string) Str::uuid(),
                    ]);
                }
            }
        } finally {
            Carbon::setTestNow($originalClock);
        }
    }
}
