<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Common columns present in every correction table.
 *
 * @property int $id
 * @property int $revision
 * @property string $reason
 * @property Carbon $corrected_at
 */
abstract class Correction extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Correction history cannot be changed. Record another correction.');
        });
        static::deleting(function (): never {
            throw new LogicException('Correction history cannot be deleted.');
        });
        static::creating(function (Correction $correction): void {
            if (trim((string) $correction->reason) === '') {
                throw new LogicException('A correction reason is required.');
            }
            if ((int) $correction->revision < 1) {
                throw new LogicException('Correction revisions start at one.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
