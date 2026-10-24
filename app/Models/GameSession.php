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
        'total_tips',
        'total_cost',
        'tips_recorded_by',
        'tips_recorded_at',
        'cost_recorded_by',
        'cost_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'closed_at' => 'datetime',
            'min_buy_in' => 'integer',
            'max_buy_in' => 'integer',
            'total_tips' => 'integer',
            'total_cost' => 'integer',
            'tips_recorded_at' => 'datetime',
            'cost_recorded_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function tipsRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tips_recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function costRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cost_recorded_by');
    }

    /**
     * @return HasMany<PlayerGame, $this>
     */
    public function playerGames(): HasMany
    {
        return $this->hasMany(PlayerGame::class);
    }

    /**
     * @return HasMany<GameTotalCorrection, $this>
     */
    public function totalCorrections(): HasMany
    {
        return $this->hasMany(GameTotalCorrection::class)
            ->orderBy('corrected_at')
            ->orderBy('id');
    }
}
