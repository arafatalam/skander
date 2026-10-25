<?php

namespace App\Actions\Games;

use App\Actions\Players\CalculatePlayerBalance;
use App\Models\Cashout;
use App\Models\CashoutCorrection;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerGameVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CorrectCashout
{
    public function __construct(private readonly CalculatePlayerBalance $balances) {}

    /** @param array<string, mixed> $input */
    public function correct(Cashout $cashout, User $admin, array $input): CashoutCorrection
    {
        Gate::forUser($admin)->authorize('correct', $cashout);
        $data = $this->validateInput($input);

        return DB::transaction(function () use ($cashout, $admin, $data) {
            $participant = $cashout->playerGameVisit()->firstOrFail()->playerGame()->firstOrFail();
            // Match ordinary game activity's game-then-player lock order.
            $game = GameSession::query()->whereKey($participant->game_session_id)->lockForUpdate()->firstOrFail();
            $player = Player::query()->whereKey($participant->player_id)->lockForUpdate()->firstOrFail();
            $current = Cashout::query()->whereKey($cashout->id)->lockForUpdate()->firstOrFail();

            $existing = CashoutCorrection::query()->where('submission_id', $data['submission_id'])->first();
            if ($existing !== null) {
                $this->ensureSubmissionMatches($existing, $current, $admin, $data);

                return $existing;
            }

            $revision = (int) ($current->corrections()->max('revision') ?? 0);
            $this->ensureRevisionMatches($revision, (int) $data['revision']);
            $warning = $this->returnWarning($current->playerGameVisit, $game, (int) $data['ending_chips']);
            $reason = $warning === null ? trim($data['reason']) : $warning."\n\n".trim($data['reason']);
            $correction = $this->saveCorrection($current, $admin, $data, $revision, $reason);

            $current->update([
                'ending_chips' => (int) $data['ending_chips'],
                'cash_paid' => (int) $data['cash_paid'],
            ]);
            app(EnsureNonNegativeGameIncome::class)->ensure($game);
            $player->balance = $this->balances->calculate($player);
            $player->save();

            return $correction;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateInput(array $input): array
    {
        return Validator::make($input, [
            'ending_chips' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'cash_paid' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'revision' => ['required', 'integer', 'min:0'],
            'submission_id' => ['required', 'uuid'],
        ])->validate();
    }

    /** @param array<string, mixed> $data */
    private function ensureSubmissionMatches(CashoutCorrection $existing, Cashout $cashout, User $admin, array $data): void
    {
        // Remove only our generated warning, retaining the complete Admin explanation.
        $reason = preg_replace(
            '/\AWarning: Corrected (?:ending chips|cashout) \$[0-9,]+ require a return buy-in of (?:exactly \$[0-9,]+|\$[0-9,]+ to \$[0-9,]+)\. The recorded \$[0-9,]+ return buy-in is unchanged\.\n\n/',
            '',
            $existing->reason,
        );

        if ($existing->cashout_id !== $cashout->id
            || $existing->corrected_by !== $admin->id
            || $existing->new_ending_chips !== (int) $data['ending_chips']
            || $existing->new_cash_paid !== (int) $data['cash_paid']
            || ($existing->reason !== trim($data['reason']) && $reason !== trim($data['reason']))
            || $existing->revision !== (int) $data['revision'] + 1) {
            throw ValidationException::withMessages([
                'submission_id' => 'This submission was already used for another correction. Reload the form.',
            ]);
        }
    }

    private function ensureRevisionMatches(int $revision, int $expectedRevision): void
    {
        if ($revision !== $expectedRevision) {
            throw ValidationException::withMessages([
                'revision' => 'This cashout has changed. Reload the form before correcting it.',
            ]);
        }
    }

    private function returnWarning(PlayerGameVisit $visit, GameSession $game, int $endingChips): ?string
    {
        $nextVisit = PlayerGameVisit::query()->where('player_game_id', $visit->player_game_id)
            ->where('visit_number', '>', $visit->visit_number)->orderBy('visit_number')->first();
        $firstBuyIn = $nextVisit?->buyIns()->orderBy('id')->first();
        if ($firstBuyIn === null) {
            return null;
        }

        // Cash and credit portions share a submission ID; later top-ups do not.
        $returnAmount = (int) $nextVisit->buyIns()->where('submission_id', $firstBuyIn->submission_id)->sum('amount');
        $minimum = max($game->min_buy_in, $endingChips);
        $maximum = max($game->max_buy_in, $endingChips);
        if ($returnAmount >= $minimum && $returnAmount <= $maximum) {
            return null;
        }

        $limits = $endingChips > $game->max_buy_in
            ? 'exactly $'.number_format($endingChips)
            : '$'.number_format($minimum).' to $'.number_format($maximum);

        return 'Warning: Corrected cashout $'.number_format($endingChips)
            .' require a return buy-in of '.$limits.'. The recorded $'.number_format($returnAmount)
            .' return buy-in is unchanged.';
    }

    /** @param array<string, mixed> $data */
    private function saveCorrection(Cashout $cashout, User $admin, array $data, int $revision, string $reason): CashoutCorrection
    {
        return $cashout->corrections()->create([
            'revision' => $revision + 1,
            'submission_id' => $data['submission_id'],
            'previous_ending_chips' => $cashout->ending_chips,
            'previous_cash_paid' => $cashout->cash_paid,
            'new_ending_chips' => (int) $data['ending_chips'],
            'new_cash_paid' => (int) $data['cash_paid'],
            'reason' => $reason,
            'corrected_by' => $admin->id,
            'corrected_at' => now(),
        ]);
    }
}
