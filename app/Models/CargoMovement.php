<?php

namespace App\Models;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use App\Observers\CargoMovementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(CargoMovementObserver::class)]
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
        'recorded_by',
        'ingest_batch_id',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IngestBatch::class, 'ingest_batch_id');
    }

    /**
     * Plain-English description of the movement, for people (activity feed, reports).
     * Eager-load tank, product, vessel and truck when listing many rows.
     */
    protected function summary(): Attribute
    {
        return Attribute::get(function (): string {
            $product = $this->product?->code?->value ?? 'cargo';
            $tank = $this->tank?->code ?? 'a tank';
            $declared = (string) $this->declared_volume;
            $isDischarge = $this->type === MovementType::Discharge;
            $vessel = $this->vessel?->name ?? 'a vessel';
            $truck = $this->truck?->plate_number ?? 'a truck';

            $what = $isDischarge
                ? "{$declared} t of {$product} from {$vessel} into {$tank}"
                : "{$declared} t of {$product} from {$tank} to truck {$truck}";

            return match ($this->status) {
                MovementStatus::Rejected => ucfirst($isDischarge ? 'discharge' : 'delivery')
                    ." of {$what} was rejected: ".($this->failure_reason ?? 'no reason recorded'),
                MovementStatus::Pending => ucfirst($isDischarge ? 'discharge' : 'delivery')." of {$what} is pending",
                default => $isDischarge
                    ? "Discharged {$what} ({$this->net_volume} t landed after {$this->loss_volume} t transit loss)"
                    : "Delivered {$what}",
            };
        });
    }
}