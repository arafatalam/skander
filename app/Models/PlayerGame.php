<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlayerGame extends Model
{
    protected $fillable = [
        'player_id',
        'game_session_id',
        'created_by',
    ];

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BuyIn, $this>
     */
    public function buyIns(): HasMany
    {
        return $this->hasMany(BuyIn::class);
    }

    /**
     * @return HasMany<PlayerGameVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(PlayerGameVisit::class)
            ->orderBy('visit_number');
    }

    /**
     * @return HasOne<PlayerGameVisit, $this>
     */
    public function latestVisit(): HasOne
    {
        return $this->hasOne(PlayerGameVisit::class)
            ->ofMany('visit_number', 'max');
    }
}
