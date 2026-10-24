<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCorrection extends Correction
{
    protected $fillable = [
        'payment_id',
        'revision',
        'submission_id',
        'previous_amount',
        'previous_direction',
        'previous_paid_at',
        'previous_note',
        'new_amount',
        'new_direction',
        'new_paid_at',
        'new_note',
        'reason',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'integer',
            'previous_paid_at' => 'date',
            'new_amount' => 'integer',
            'new_paid_at' => 'date',
            'revision' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
