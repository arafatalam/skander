<?php

namespace App\Actions\Games;

use App\Actions\Corrections\CheckHistoricalBuyIns;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGame;
use App\Models\PlayerGameVisit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddHistoricalActivity
{
    public function __construct(private readonly CheckHistoricalBuyIns $checks) {}

    /** Called inside RecordCorrection's locked transaction with validated input.
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function add(GameSession $game, Player $player, User $admin, array $data): array
    {
        Gate::forUser($admin)->authorize('addHistoricalActivity', $game);
        $participant = PlayerGame::where('game_session_id', $game->id)->where('player_id', $player->id)->first();
        if ($this->checks->sequenceVersion($participant) !== $data['sequence_version']) {
            throw ValidationException::withMessages(['sequence_version' => 'This player’s visits have changed. Reload the list before adding activity.']);
        }
        $before = $participant === null ? [] : $this->snapshot($participant);
        if ($data['kind'] === 'missing_player') {
            if ($participant !== null) {
                throw ValidationException::withMessages(['player_id' => 'This player is already recorded. Choose Add missing visit or Add missing buy-in.']);
            }
            $participant = PlayerGame::create(['game_session_id' => $game->id, 'player_id' => $player->id, 'created_by' => $admin->id]);
            $this->addVisits($participant, $admin, $data['visits'], 1);
        } else {
            if ($participant === null) {
                throw ValidationException::withMessages(['player_id' => 'Choose a player already recorded in this game.']);
            }
            if ($data['kind'] === 'missing_visit') {
                $count = $participant->visits()->count();
                if ($data['position'] > $count + 1) {
                    throw ValidationException::withMessages(['position' => 'Choose a position in the existing visit sequence.']);
                }
                // An unfinished visit must stay last; an inserted completed visit can precede it.
                if ($participant->visits()->where('visit_number', '<', $data['position'])->doesntHave('cashout')->exists()) {
                    throw ValidationException::withMessages(['position' => 'Insert before the unfinished visit, or record its cashout first.']);
                }
                foreach ($participant->visits()->reorder('visit_number', 'desc')->where('visit_number', '>=', $data['position'])->get() as $visit) {
                    $visit->update(['visit_number' => $visit->visit_number + count($data['visits'])]);
                }
                $this->addVisits($participant, $admin, $data['visits'], $data['position']);
            } else {
                $visit = $participant->visits()->whereKey($data['visit_id'])->firstOrFail();
                if ($data['kind'] === 'missing_buy_in') {
                    $this->addBuyIn($visit, $admin, $data);
                } else {
                    if ($visit->cashout()->exists()) {
                        throw ValidationException::withMessages(['visit_id' => 'This visit already has a cashout. Correct the existing cashout instead.']);
                    }
                    if (! $visit->buyIns()->exists()) {
                        throw ValidationException::withMessages(['visit_id' => 'Record this visit’s buy-in first.']);
                    }
                    $this->addCashout($visit, $admin, $data);
                }
            }
        }

        return ['before' => $before, 'after' => $this->snapshot($participant), 'warnings' => $this->checks->warnings($participant)];
    }

    /** @param list<array<string, mixed>> $visits */
    private function addVisits(PlayerGame $participant, User $admin, array $visits, int $position): void
    {
        foreach ($visits as $index => $data) {
            $visit = $participant->visits()->create([
                'visit_number' => $position + $index, 'opened_at' => now(), 'created_by' => $admin->id,
            ]);
            foreach ($data['buy_ins'] as $buyIn) {
                $this->addBuyIn($visit, $admin, $buyIn);
            }
            $this->addCashout($visit, $admin, $data);
        }
    }

    /** @param array<string, mixed> $data */
    private function addBuyIn(PlayerGameVisit $visit, User $admin, array $data): void
    {
        if ($data['cash_amount'] + $data['credit_amount'] === 0) {
            throw ValidationException::withMessages(['buy_ins' => 'Every buy-in must have a combined cash and credit amount greater than $0.']);
        }
        $submission = (string) Str::uuid();
        foreach (['cash', 'credit'] as $type) {
            if ($data[$type.'_amount'] === 0) {
                continue;
            }
            $visit->buyIns()->create([
                'player_game_id' => $visit->player_game_id, 'submission_id' => $submission,
                'amount' => $data[$type.'_amount'], 'type' => $type, 'created_by' => $admin->id,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function addCashout(PlayerGameVisit $visit, User $admin, array $data): void
    {
        $visit->cashout()->create([
            'ending_chips' => $data['ending_chips'], 'cash_paid' => $data['cash_paid'],
            'creation_method' => 'manual', 'cashed_out_at' => now(), 'created_by' => $admin->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(PlayerGame $participant): array
    {
        return ['participant_id' => $participant->id, 'visits' => $participant->visits()->with(['buyIns', 'cashout'])->get()->map(fn ($visit) => [
            'id' => $visit->id, 'visit_number' => $visit->visit_number,
            'buy_ins' => $visit->buyIns->map(fn ($row) => $row->only(['id', 'submission_id', 'type', 'amount']))->all(),
            'cashout' => $visit->cashout?->only(['id', 'ending_chips', 'cash_paid']),
        ])->all()];
    }
}
