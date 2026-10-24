<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashoutCorrection extends Correction
{
    protected $fillable = [
        'cashout_id',
        'revision',
        'submission_id',
        'previous_ending_chips',
        'previous_cash_paid',
        'new_ending_chips',
        'new_cash_paid',
        'reason',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_ending_chips' => 'integer',
            'previous_cash_paid' => 'integer',
            'new_ending_chips' => 'integer',
            'new_cash_paid' => 'integer',
            'revision' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cashout, $this> */
    public function cashout(): BelongsTo
    {
        return $this->belongsTo(Cashout::class);
    }
}
