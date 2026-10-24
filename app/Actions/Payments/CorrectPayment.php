<?php

namespace App\Actions\Payments;

use App\Actions\Players\CalculatePlayerBalance;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CorrectPayment
{
    public function __construct(private readonly CalculatePlayerBalance $balances) {}

    /** @param array<string, mixed> $input */
    public function correct(Payment $payment, User $admin, array $input): void
    {
        Gate::forUser($admin)->authorize('correct', $payment);
        $data = Validator::make($input, [
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'direction' => ['required', Rule::in(['house_pays_player', 'house_collects_from_player'])],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'revision' => ['required', 'integer', 'min:0'],
            'submission_id' => ['required', 'uuid'],
        ])->validate();

        DB::transaction(function () use ($payment, $admin, $data) {
            $player = Player::query()->whereKey($payment->player_id)->lockForUpdate()->firstOrFail();
            $current = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $existing = PaymentCorrection::query()->where('submission_id', $data['submission_id'])->first();
            if ($existing !== null) {
                if ($existing->payment_id !== $current->id || $existing->corrected_by !== $admin->id
                    || $existing->new_amount !== (int) $data['amount']
                    || $existing->new_direction !== $data['direction']
                    || $existing->new_paid_at->toDateString() !== $data['paid_at']
                    || $existing->new_note !== ($data['note'] ?? null)
                    || $existing->reason !== trim($data['reason'])
                    || $existing->revision !== (int) $data['revision'] + 1) {
                    throw ValidationException::withMessages(['submission_id' => 'This submission was already used for another correction. Reload the form.']);
                }

                return;
            }
            $revision = (int) ($current->corrections()->max('revision') ?? 0);
            if ($revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'This payment has changed. Reload the form before correcting it.']);
            }

            $current->corrections()->create([
                'revision' => $revision + 1, 'submission_id' => $data['submission_id'],
                'previous_amount' => $current->amount, 'new_amount' => (int) $data['amount'],
                'previous_direction' => $current->direction, 'new_direction' => $data['direction'],
                'previous_paid_at' => $current->paid_at, 'new_paid_at' => $data['paid_at'],
                'previous_note' => $current->note, 'new_note' => $data['note'] ?? null,
                'reason' => trim($data['reason']), 'corrected_by' => $admin->id, 'corrected_at' => now(),
            ]);
            $current->update([
                'amount' => (int) $data['amount'], 'direction' => $data['direction'],
                'paid_at' => $data['paid_at'], 'note' => $data['note'] ?? null,
            ]);
            $player->balance = $this->balances->calculate($player);
            $player->save();
        }, attempts: 3);
    }
}
