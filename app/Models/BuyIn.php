<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyIn extends Model
{
    protected $fillable = [
        'player_game_id',
        'player_game_visit_id',
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

    /**
     * @return BelongsTo<PlayerGame, $this>
     */
    public function playerGame(): BelongsTo
    {
        return $this->belongsTo(PlayerGame::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<PlayerGameVisit, $this>
     */
    public function playerGameVisit(): BelongsTo
    {
        return $this->belongsTo(PlayerGameVisit::class);
    }
}
