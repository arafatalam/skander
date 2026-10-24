<?php

namespace App\Http\Requests\Games;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordGameTotalsRequest extends FormRequest
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
            'total_tips' => [
                'nullable',
                'required_without:total_cost',
                'integer',
                'min:0',
                'max:2147483647',
            ],
            'total_cost' => [
                'nullable',
                'required_without:total_tips',
                'integer',
                'min:0',
                'max:2147483647',
            ],
        ];
    }
}
