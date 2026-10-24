<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlayerGameVisit extends Model
{
    protected $fillable = [
        'player_game_id',
        'visit_number',
        'opened_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_number' => 'integer',
            'opened_at' => 'datetime',
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
     * @return HasMany<BuyIn, $this>
     */
    public function buyIns(): HasMany
    {
        return $this->hasMany(BuyIn::class);
    }

    /**
     * @return HasOne<Cashout, $this>
     */
    public function cashout(): HasOne
    {
        return $this->hasOne(Cashout::class);
    }
}
