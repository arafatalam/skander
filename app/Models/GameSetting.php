<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSetting extends Model
{
    protected $fillable = [
        'default_min_buy_in',
        'default_max_buy_in',
    ];

    protected function casts(): array
    {
        return [
            'default_min_buy_in' => 'integer',
            'default_max_buy_in' => 'integer',
        ];
    }
}
