<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyIn extends Model
{
    protected $fillable = [
        'player_game_id',
        'amount',
        'type',
        'submission_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function playerGame(): BelongsTo
    {
        return $this->belongsTo(PlayerGame::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
