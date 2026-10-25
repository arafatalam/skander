<?php

namespace App\Http\Requests\Players;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPlayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Routes enforce the PlayerPolicy abilities.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'owing' => ['nullable', Rule::in(['all', 'collect', 'pay'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'from' => ['nullable', Rule::in(['dashboard'])],
        ];
    }
}
