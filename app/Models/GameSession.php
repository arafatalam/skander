<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'game_date',
    'game_number',
    'game_name',
    'status',
    'created_by',
    'closed_by',
    'closed_at',
])]
class GameSession extends Model
{
    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
