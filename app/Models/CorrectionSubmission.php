<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CorrectionSubmission extends Model
{
    protected $fillable = [
        'submission_id', 'kind', 'request_hash', 'game_session_id', 'player_id',
        'target_id', 'before', 'after', 'warnings', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'warnings' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Correction submissions cannot be changed.');
        });
        static::deleting(function (): never {
            throw new LogicException('Correction submissions cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
