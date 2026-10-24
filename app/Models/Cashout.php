<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cashout extends Model
{
    protected $fillable = [
        'player_game_visit_id',
        'ending_chips',
        'cash_paid',
        'creation_method',
        'cashed_out_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ending_chips' => 'integer',
            'cash_paid' => 'integer',
            'cashed_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlayerGameVisit, $this>
     */
    public function playerGameVisit(): BelongsTo
    {
        return $this->belongsTo(PlayerGameVisit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CashoutCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(CashoutCorrection::class)->orderBy('revision');
    }
}
