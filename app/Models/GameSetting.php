<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSetting extends Model
{
    public const HOUSE_DEFAULTS_KEY = 'house_defaults';

    protected $fillable = [
        'key',
        'default_min_buy_in',
        'default_max_buy_in',
    ];

    public static function houseDefaults(): self
    {
        return static::query()->firstOrCreate(
            ['key' => self::HOUSE_DEFAULTS_KEY],
            ['default_min_buy_in' => 100, 'default_max_buy_in' => 500],
        );
    }

    protected function casts(): array
    {
        return [
            'default_min_buy_in' => 'integer',
            'default_max_buy_in' => 'integer',
        ];
    }
}
