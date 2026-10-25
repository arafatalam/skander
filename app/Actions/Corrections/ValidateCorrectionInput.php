<?php

namespace App\Actions\Corrections;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ValidateCorrectionInput
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $kind = $input['kind'] ?? '';
        $money = ['required', 'integer', 'min:0', 'max:2147483647'];
        $rules = [
            'kind' => ['required', Rule::in(['buy_in', 'cashout', 'payment', 'total_tips', 'total_cost', 'missing_player', 'missing_visit', 'missing_buy_in', 'missing_cashout', 'missing_payment'])],
            'submission_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
        ];
        if (! in_array($kind, ['payment', 'missing_payment'], true)) {
            $rules['game_id'] = ['required', 'integer', 'exists:game_sessions,id'];
        }
        if (! in_array($kind, ['total_tips', 'total_cost'], true)) {
            $rules['player_id'] = ['required', 'integer', 'exists:players,id'];
        }
        if (in_array($kind, ['buy_in', 'cashout', 'payment'], true)) {
            $rules['target_id'] = ['required', 'integer', 'min:1'];
        }
        if (! str_starts_with($kind, 'missing_')) {
            $rules['revision'] = ['required', 'integer', 'min:0'];
        }
        if (in_array($kind, ['buy_in', 'missing_buy_in'], true)) {
            $rules['cash_amount'] = $money;
            $rules['credit_amount'] = $money;
        }
        if (in_array($kind, ['cashout', 'missing_cashout'], true)) {
            $rules['ending_chips'] = $money;
            $rules['cash_paid'] = $money;
        }
        if (in_array($kind, ['missing_buy_in', 'missing_cashout'], true)) {
            $rules['visit_id'] = ['required', 'integer', 'min:1'];
        }
        if (in_array($kind, ['payment', 'missing_payment'], true)) {
            $rules['amount'] = ['required', 'integer', 'min:1', 'max:2147483647'];
            $rules['direction'] = ['required', Rule::in(['house_pays_player', 'house_collects_from_player'])];
            $rules['paid_at'] = ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()];
            $rules['note'] = ['nullable', 'string', 'max:2000'];
        }
        if (in_array($kind, ['total_tips', 'total_cost'], true)) {
            $rules['amount'] = $money;
        }
        if (in_array($kind, ['missing_player', 'missing_visit'], true)) {
            $rules['visits'] = ['required', 'array', 'min:1', 'max:50'];
            $rules['visits.*.buy_ins'] = ['required', 'array', 'min:1', 'max:100'];
            $rules['visits.*.buy_ins.*.cash_amount'] = $money;
            $rules['visits.*.buy_ins.*.credit_amount'] = $money;
            $rules['visits.*.ending_chips'] = $money;
            $rules['visits.*.cash_paid'] = $money;
        }
        if ($kind === 'missing_visit') {
            $rules['position'] = ['required', 'integer', 'min:1'];
        }
        if (in_array($kind, ['missing_player', 'missing_visit', 'missing_buy_in', 'missing_cashout'], true)) {
            $rules['sequence_version'] = ['required', 'string', 'size:64'];
        }
        $data = Validator::make($input, $rules, [], [
            'game_id' => 'game', 'player_id' => 'player', 'visit_id' => 'visit',
            'target_id' => 'selected record', 'sequence_version' => 'visit sequence',
            'cash_amount' => 'cash amount', 'credit_amount' => 'credit amount',
            'ending_chips' => 'ending chips', 'cash_paid' => 'cash paid',
            'visits.*.buy_ins' => 'buy-ins for each visit',
            'visits.*.buy_ins.*.cash_amount' => 'cash amount for each buy-in',
            'visits.*.buy_ins.*.credit_amount' => 'credit amount for each buy-in',
            'visits.*.ending_chips' => 'ending chips for each visit',
            'visits.*.cash_paid' => 'cash paid for each visit',
        ])->validate();
        $data['reason'] = trim($data['reason']);

        return $this->normalize($data);
    }

    /** @template TKey of array-key
     * @param  array<TKey, mixed>  $data
     * @return array<TKey, mixed>
     */
    private function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value);
            } elseif (in_array($key, ['game_id', 'player_id', 'target_id', 'visit_id', 'revision', 'position', 'cash_amount', 'credit_amount', 'ending_chips', 'cash_paid', 'amount'], true)) {
                $data[$key] = (int) $value;
            }
        }
        ksort($data);

        return $data;
    }
}
