<?php

namespace App\Http\Requests\BuyIns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBuyInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_session_id' => ['required', 'integer', 'exists:game_sessions,id'],
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'cash_amount' => ['required', 'integer', 'min:0'],
            'credit_amount' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cash = $this->integer('cash_amount');
                $credit = $this->integer('credit_amount');

                if ($cash === 0 && $credit === 0) {
                    $validator->errors()->add(
                        'cash_amount',
                        'Enter a cash or credit amount greater than zero.'
                    );
                }
            },
        ];
    }
}
