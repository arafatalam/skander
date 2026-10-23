<?php

namespace App\Http\Requests\GameSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameSettingRequest extends FormRequest
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
            'default_min_buy_in' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
            ],
            'default_max_buy_in' => [
                'required',
                'integer',
                'min:1',
                'max:2147483647',
                'gte:default_min_buy_in',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'default_min_buy_in.max' => 'Please enter a smaller minimum buy-in.',
            'default_max_buy_in.max' => 'Please enter a smaller maximum buy-in.',
            'default_max_buy_in.gte' => 'The maximum buy-in must be at least the minimum buy-in.',
        ];
    }
}
