<?php

namespace App\Actions\Corrections;

use App\Actions\Games\AddHistoricalActivity;
use App\Actions\Games\CorrectBuyInGroup;
use App\Actions\Games\CorrectCashout;
use App\Actions\Games\CorrectGameTotal;
use App\Actions\Games\EnsureNonNegativeGameIncome;
use App\Actions\Payments\CorrectPayment;
use App\Actions\Players\CalculatePlayerBalance;
use App\Models\Cashout;
use App\Models\CorrectionSubmission;
use App\Models\GameSession;
use App\Models\Payment;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordCorrection
{
    public function __construct(private readonly ValidateCorrectionInput $input, private readonly CalculatePlayerBalance $balances) {}

    /** @param array<string, mixed> $input */
    public function record(User $admin, array $input): CorrectionSubmission
    {
        Gate::forUser($admin)->authorize('create', CorrectionSubmission::class);
        $data = $this->input->validate($input);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($admin, $data, $hash) {
                $game = isset($data['game_id']) ? GameSession::whereKey($data['game_id'])->lockForUpdate()->firstOrFail() : null;
                $player = isset($data['player_id']) ? Player::whereKey($data['player_id'])->lockForUpdate()->firstOrFail() : null;
                $existing = CorrectionSubmission::where('submission_id', $data['submission_id'])->first();
                if ($existing !== null) {
                    if ($existing->request_hash !== $hash || $existing->created_by !== $admin->id) {
                        throw ValidationException::withMessages(['submission_id' => 'This submission was already used. Reload the form.']);
                    }

                    return $existing;
                }
                $result = $this->apply($game, $player, $admin, $data);
                if ($game !== null) {
                    app(EnsureNonNegativeGameIncome::class)->ensure($game);
                }
                if ($player !== null) {
                    $player->balance = $this->balances->calculate($player);
                    $player->save();
                }

                return CorrectionSubmission::create([
                    'submission_id' => $data['submission_id'], 'kind' => $data['kind'], 'request_hash' => $hash,
                    'game_session_id' => $game?->id, 'player_id' => $player?->id,
                    'target_id' => $data['target_id'] ?? $result['target_id'] ?? $game?->id,
                    'before' => $result['before'], 'after' => $result['after'], 'warnings' => $result['warnings'],
                    'reason' => ($result['warnings'] === [] ? '' : implode("\n", $result['warnings'])."\n\n").$data['reason'],
                    'created_by' => $admin->id,
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! CorrectionSubmission::where('submission_id', $data['submission_id'])->exists()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['submission_id' => 'This submission was already used. Reload the list.']);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function apply(?GameSession $game, ?Player $player, User $admin, array $data): array
    {
        if ($data['kind'] === 'missing_payment') {
            Gate::forUser($admin)->authorize('create', Payment::class);
            // Historical payments describe an actual past transfer, independent of today's owing direction.
            $payment = $player->payments()->create([
                'amount' => $data['amount'], 'direction' => $data['direction'], 'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null, 'submission_id' => $data['submission_id'], 'created_by' => $admin->id,
            ]);

            return ['before' => [], 'after' => $payment->only(['id', 'amount', 'direction', 'paid_at', 'note']), 'warnings' => [], 'target_id' => $payment->id];
        }
        if (str_starts_with($data['kind'], 'missing_')) {
            return app(AddHistoricalActivity::class)->add($game, $player, $admin, $data);
        }
        if (in_array($data['kind'], ['total_tips', 'total_cost'], true)) {
            return app(CorrectGameTotal::class)->correct($game, $admin, $data);
        }
        if ($data['kind'] === 'payment') {
            $payment = $player->payments()->whereKey($data['target_id'])->firstOrFail();
            $before = $payment->only(['amount', 'direction', 'paid_at', 'note']);
            app(CorrectPayment::class)->correct($payment, $admin, $data);

            return ['before' => $before, 'after' => $payment->fresh()->only(['amount', 'direction', 'paid_at', 'note']), 'warnings' => []];
        }
        $participant = $player->playerGames()->where('game_session_id', $game->id)->firstOrFail();
        if ($data['kind'] === 'buy_in') {
            $buyIn = $participant->buyIns()->whereKey($data['target_id'])->firstOrFail();

            return app(CorrectBuyInGroup::class)->correct($buyIn, $admin, $data);
        }
        $cashout = Cashout::whereKey($data['target_id'])->whereHas('playerGameVisit', fn ($query) => $query->where('player_game_id', $participant->id))->firstOrFail();
        $before = $cashout->only(['ending_chips', 'cash_paid']);
        $correction = app(CorrectCashout::class)->correct($cashout, $admin, $data);
        $warnings = $correction->reason === $data['reason'] ? [] : [explode("\n\n", $correction->reason, 2)[0]];

        return ['before' => $before, 'after' => $cashout->fresh()->only(['ending_chips', 'cash_paid']), 'warnings' => $warnings];
    }
}
