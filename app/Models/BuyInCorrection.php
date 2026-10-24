<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyInCorrection extends Correction
{
    protected $fillable = [
        'buy_in_id',
        'revision',
        'submission_id',
        'previous_amount',
        'previous_type',
        'new_amount',
        'new_type',
        'reason',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'integer',
            'new_amount' => 'integer',
            'revision' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BuyIn, $this> */
    public function buyIn(): BelongsTo
    {
        return $this->belongsTo(BuyIn::class);
    }
}
