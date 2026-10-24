<?php

namespace App\Http\Requests\BuyIns;

use App\Models\GameSession;
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
            'cash_amount' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'credit_amount' => ['required', 'integer', 'min:0', 'max:2147483647'],
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

                $game = GameSession::query()
                    ->findOrFail($this->integer('game_session_id'));

                $total = $cash + $credit;

                if ($total < $game->min_buy_in || $total > $game->max_buy_in) {
                    $validator->errors()->add(
                        'buy_in_total',
                        "The combined cash and credit amount must be between \${$game->min_buy_in} and \${$game->max_buy_in}."
                    );
                }
            },
        ];
    }
}
