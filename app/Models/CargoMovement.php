<?php

namespace App\Models;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'type',
        'tank_id',
        'product_id',
        'vessel_id',
        'truck_id',
        'declared_volume',
        'loss_volume',
        'net_volume',
        'occurred_at',
        'processed_at',
        'status',
        'failure_reason',
        'volume_before',
        'volume_after',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'status' => MovementStatus::class,
            'declared_volume' => 'decimal:3',
            'loss_volume' => 'decimal:3',
            'net_volume' => 'decimal:3',
            'volume_before' => 'decimal:3',
            'volume_after' => 'decimal:3',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}