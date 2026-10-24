<?php

namespace App\Http\Requests\Players;

use App\Models\Player;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');

        return $player instanceof Player
            && ($this->user()?->can('update', $player) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $player = $this->route('player');
        abort_unless($player instanceof Player, 404);

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('players', 'email')->ignore($player),
            ],
        ];
    }
}
