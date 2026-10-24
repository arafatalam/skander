<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read CarbonInterface $game_date
 */
class GameSession extends Model
{
    protected $fillable = [
        'game_date',
        'game_number',
        'game_name',
        'status',
        'min_buy_in',
        'max_buy_in',
        'created_by',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'closed_at' => 'datetime',
            'min_buy_in' => 'integer',
            'max_buy_in' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<PlayerGame, $this>
     */
    public function playerGames(): HasMany
    {
        return $this->hasMany(PlayerGame::class);
    }
}
