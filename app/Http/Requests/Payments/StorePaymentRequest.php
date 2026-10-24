<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route will enforce PaymentPolicy::create().
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_id' => [
                'required',
                'uuid',
            ],
            'player_id' => [
                'required',
                'integer',
                'exists:players,id',
            ],
            'amount' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
            ],
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
            'note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.integer' => 'Enter an amount in whole dollars.',
            'amount.min' => 'The payment must be at least $1.',
            'paid_at.before_or_equal' => 'The payment date cannot be in the future.',
        ];
    }
}
