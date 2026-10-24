<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTotalCorrection extends Correction
{
    protected $fillable = [
        'game_session_id',
        'field',
        'revision',
        'submission_id',
        'previous_amount',
        'new_amount',
        'reason',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'previous_amount' => 'integer',
            'new_amount' => 'integer',
            'corrected_at' => 'datetime',
        ];
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
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
