<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function record(
        Player $player,
        User $admin,
        int $amount,
        string $direction,
        string $paidAt,
        string $submissionId,
        ?string $note = null,
    ): Payment {
        Gate::forUser($admin)->authorize('create', Payment::class);

        $this->validateInput($amount, $direction, $paidAt, $submissionId, $note);

        try {
            return DB::transaction(function () use (
                $player,
                $admin,
                $amount,
                $direction,
                $paidAt,
                $submissionId,
                $note,
            ) {
                $currentPlayer = $this->lockPlayer($player);

                $existingPayment = $this->findExistingPayment(
                    $submissionId,
                    $currentPlayer,
                    $admin,
                    $amount,
                    $direction,
                    $paidAt,
                    $note,
                );

                if ($existingPayment !== null) {
                    return $existingPayment;
                }

                $this->ensureDirectionMatchesBalance($currentPlayer, $direction);

                $newBalance = $this->calculateBalance(
                    $currentPlayer->balance,
                    $amount,
                    $direction,
                );

                $payment = $this->savePayment(
                    $currentPlayer,
                    $admin,
                    $amount,
                    $direction,
                    $paidAt,
                    $submissionId,
                    $note,
                );

                $currentPlayer->balance = $newBalance;
                $currentPlayer->save();

                return $payment;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! Payment::where('submission_id', $submissionId)->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'submission_id' => 'This submission ID has already been used. Refresh the form before recording a new payment.',
            ]);
        }
    }

    private function findExistingPayment(
        string $submissionId,
        Player $player,
        User $admin,
        int $amount,
        string $direction,
        string $paidAt,
        ?string $note,
    ): ?Payment {
        $payment = Payment::query()
            ->where('submission_id', $submissionId)
            ->lockForUpdate()
            ->first();

        if ($payment === null) {
            return null;
        }

        $matches = $payment->player_id === $player->id
            && $payment->created_by === $admin->id
            && $payment->amount === $amount
            && $payment->direction === $direction
            && $payment->paid_at->toDateString() === $paidAt
            && $payment->note === $note;

        if (! $matches) {
            throw ValidationException::withMessages([
                'submission_id' => 'This submission ID belongs to a different payment. Refresh the form before recording a new payment.',
            ]);
        }

        return $payment;
    }

    private function validateInput(
        int $amount,
        string $direction,
        string $paidAt,
        string $submissionId,
        ?string $note,
    ): void {
        Validator::make([
            'submission_id' => $submissionId,
            'amount' => $amount,
            'direction' => $direction,
            'paid_at' => $paidAt,
            'note' => $note,
        ], [
            'submission_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'direction' => [
                'required',
                Rule::in([
                    'house_pays_player',
                    'house_collects_from_player',
                ]),
            ],
            'paid_at' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.today()->toDateString(),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ])->validate();
    }

    private function lockPlayer(Player $player): Player
    {
        return Player::query()
            ->whereKey($player->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureDirectionMatchesBalance(
        Player $player,
        string $direction,
    ): void {
        if ($player->balance === 0) {
            throw ValidationException::withMessages([
                'player_id' => 'This player has no outstanding balance.',
            ]);
        }

        $expectedDirection = $player->balance > 0
            ? 'house_pays_player'
            : 'house_collects_from_player';

        if ($direction !== $expectedDirection) {
            throw ValidationException::withMessages([
                'direction' => 'The player’s balance has changed. Refresh the form and try again.',
            ]);
        }
    }

    private function calculateBalance(
        int $balance,
        int $amount,
        string $direction,
    ): int {
        $housePays = $direction === 'house_pays_player';

        if (
            ($housePays && $balance < PHP_INT_MIN + $amount)
            || (! $housePays && $balance > PHP_INT_MAX - $amount)
        ) {
            throw ValidationException::withMessages([
                'amount' => 'This payment would exceed the supported balance range.',
            ]);
        }

        return $housePays
            ? $balance - $amount
            : $balance + $amount;
    }

    private function savePayment(
        Player $player,
        User $admin,
        int $amount,
        string $direction,
        string $paidAt,
        string $submissionId,
        ?string $note,
    ): Payment {
        return Payment::create([
            'submission_id' => $submissionId,
            'player_id' => $player->id,
            'amount' => $amount,
            'direction' => $direction,
            'paid_at' => $paidAt,
            'created_by' => $admin->id,
            'note' => $note,
        ]);
    }
}
