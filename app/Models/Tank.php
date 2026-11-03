<?php

namespace App\Models;

use App\Domain\Cargo\Enums\TankStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tank extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'product_id',
        'current_vessel_id',
        'capacity',
        'current_volume',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:3',
            'current_volume' => 'decimal:3',
            'status' => TankStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currentVessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'current_vessel_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }

    // Decimal-safe helpers (bcmath; never PHP floats for volumes)
    public function isEmpty(): bool
    {
        return bccomp((string) $this->current_volume, '0', 3) === 0;
    }

    public function availableCapacity(): string
    {
        return bcsub((string) $this->capacity, (string) $this->current_volume, 3);
    }
}