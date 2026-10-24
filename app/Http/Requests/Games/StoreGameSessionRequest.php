<?php

namespace App\Http\Requests\Games;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameSessionRequest extends FormRequest
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
            'game_date' => ['required', 'date_format:Y-m-d'],
            'game_name' => ['required', 'string', 'max:16'],
            'use_custom_limits' => ['required', 'boolean'],
            'confirmed_min_buy_in' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'confirmed_max_buy_in' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'min_buy_in' => [
                'exclude_unless:use_custom_limits,true',
                'required',
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'max_buy_in' => [
                'exclude_unless:use_custom_limits,true',
                'required',
                'integer',
                'min:1',
                'max:2147483647',
                'gte:min_buy_in',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'min_buy_in.max' => 'Please enter a smaller minimum buy-in.',
            'max_buy_in.max' => 'Please enter a smaller maximum buy-in.',
            'max_buy_in.gte' => 'The maximum buy-in must be at least the minimum buy-in.',
        ];
    }
}
