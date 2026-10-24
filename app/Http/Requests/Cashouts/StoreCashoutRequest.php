<?php

namespace App\Http\Requests\Cashouts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashoutRequest extends FormRequest
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
            'game_session_id' => [
                'required',
                'integer',
                'exists:game_sessions,id',
            ],
            'player_id' => [
                'required',
                'integer',
                'exists:players,id',
            ],
            'ending_chips' => [
                'required',
                'integer',
                'min:0',
                'max:2147483647',
            ],
            'cash_paid' => [
                'required',
                'integer',
                'min:0',
                'max:2147483647',
            ],
            'expected_visit_id' => [
                'required',
                'integer',
                'exists:player_game_visits,id',
            ],
        ];
    }
}
