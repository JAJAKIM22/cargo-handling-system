<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One run of the Ingest page: what was submitted, what was applied, what was rejected and why. */
class IngestBatch extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'total_rows',
        'applied_count',
        'rejected_count',
        'skipped_count',
        'rejected',
        'skipped',
    ];

    protected function casts(): array
    {
        return [
            'rejected' => 'array',
            'skipped' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }
}