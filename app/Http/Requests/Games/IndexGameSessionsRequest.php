<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGameSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The routes enforce GameSessionPolicy abilities.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from_date') ? ['after_or_equal:from_date'] : [])],
            'status' => ['nullable', Rule::in(['all', 'open', 'closed'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['to_date.after_or_equal' => 'To date must be on or after From date.'];
    }
}
