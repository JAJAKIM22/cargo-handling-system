<?php

namespace App\Domain\Cargo\Services;

use App\Domain\Cargo\DTOs\MovementData;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use App\Domain\Cargo\Enums\TankStatus;
use App\Domain\Cargo\Exceptions\CargoException;
use App\Domain\Cargo\Exceptions\DuplicateMovementException;
use App\Domain\Cargo\Exceptions\InsufficientStockException;
use App\Domain\Cargo\Exceptions\TankAssignmentConflictException;
use App\Domain\Cargo\Exceptions\TankCapacityExceededException;
use App\Domain\Cargo\Exceptions\TankNotFoundException;
use App\Models\CargoMovement;
use App\Models\Tank;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The only code allowed to change a tank's balance.
 *
 * Atomicity: every movement runs in a DB transaction that first takes a row lock on the tank
 * (SELECT ... FOR UPDATE). Concurrent movements on the same tank queue up behind each other,
 * so two requests can never both pass the capacity check against the same stale balance.
 */
final class TankLedgerService
{
    public function __construct(private readonly VolumetricLossCalculator $calculator)
    {
    }

    /**
     * @throws CargoException when a business rule is violated (nothing is written in that case)
     */
    public function apply(MovementData $data): CargoMovement
    {
        return DB::transaction(function () use ($data): CargoMovement {
            $tank = Tank::query()->lockForUpdate()->find($data->tankId)
                ?? throw TankNotFoundException::forId($data->tankId);

            // Checked AFTER the lock so two identical submissions can't both slip through.
            if (CargoMovement::query()->where('reference', $data->reference)->exists()) {
                throw DuplicateMovementException::forReference($data->reference);
            }

            return match ($data->type) {
                MovementType::Discharge => $this->applyDischarge($tank, $data),
                MovementType::Delivery => $this->applyDelivery($tank, $data),
            };
        });
    }

    /**
     * Persist a rejected movement for the audit trail. Best effort: returns null when the
     * row cannot be stored (unknown tank/product, duplicate reference, ...).
     */
    public function recordRejection(MovementData $data, CargoException $reason): ?CargoMovement
    {
        $tank = Tank::query()->find($data->tankId);
        $productId = $data->productId ?? $tank?->product_id;

        if ($tank === null || $productId === null) {
            return null;
        }

        if (CargoMovement::query()->where('reference', $data->reference)->exists()) {
            return null;
        }

        $isDischarge = $data->type === MovementType::Discharge;
        $loss = $isDischarge ? $this->calculator->lossFor($data->declaredVolume) : '0.000';

        try {
            return CargoMovement::create([
                'reference' => $data->reference,
                'type' => $data->type,
                'tank_id' => $tank->id,
                'product_id' => $productId,
                'vessel_id' => $isDischarge ? $data->vesselId : null,
                'truck_id' => $isDischarge ? null : $data->truckId,
                'declared_volume' => $data->declaredVolume,
                'loss_volume' => $loss,
                'net_volume' => bcsub($data->declaredVolume, $loss, 3),
                'occurred_at' => $data->occurredAt,
                'processed_at' => now(),
                'status' => MovementStatus::Rejected,
                'failure_reason' => $reason->getMessage(),
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    private function applyDischarge(Tank $tank, MovementData $data): CargoMovement
    {
        $before = (string) $tank->current_volume;

        // A loaded tank only accepts more of the SAME product from the SAME vessel.
        if (! $tank->isEmpty()) {
            if ((int) $tank->product_id !== $data->productId) {
                throw TankAssignmentConflictException::productMismatch($tank, $data->productId);
            }
            if ((int) $tank->current_vessel_id !== $data->vesselId) {
                throw TankAssignmentConflictException::vesselMismatch($tank, $data->vesselId);
            }
        }

        // 0.5% transit loss: the NET amount is what lands in the tank.
        $loss = $this->calculator->lossFor($data->declaredVolume);
        $net = bcsub($data->declaredVolume, $loss, VolumetricLossCalculator::SCALE);
        $after = bcadd($before, $net, VolumetricLossCalculator::SCALE);

        if (bccomp($after, (string) $tank->capacity, VolumetricLossCalculator::SCALE) === 1) {
            throw TankCapacityExceededException::forTank($tank, $net);
        }

        // Empty tank: this discharge decides its product and vessel.
        $tank->product_id = $data->productId;
        $tank->current_vessel_id = $data->vesselId;
        $this->saveTank($tank, $after);

        return $this->record($tank, $data, $data->productId, $loss, $net, $before, $after);
    }

    private function applyDelivery(Tank $tank, MovementData $data): CargoMovement
    {
        $before = (string) $tank->current_volume;

        if ($tank->isEmpty()) {
            throw InsufficientStockException::forTank($tank, $data->declaredVolume);
        }

        if ($data->productId !== null && (int) $tank->product_id !== $data->productId) {
            throw TankAssignmentConflictException::productMismatch($tank, $data->productId);
        }

        // No transit loss on deliveries.
        $loss = '0.000';
        $net = $data->declaredVolume;
        $after = bcsub($before, $net, VolumetricLossCalculator::SCALE);

        if (bccomp($after, '0', VolumetricLossCalculator::SCALE) === -1) {
            throw InsufficientStockException::forTank($tank, $data->declaredVolume);
        }

        $productId = (int) $tank->product_id;

        // Depleted to zero: the tank is free to take any product/vessel again.
        if (bccomp($after, '0', VolumetricLossCalculator::SCALE) === 0) {
            $tank->product_id = null;
            $tank->current_vessel_id = null;
        }

        $this->saveTank($tank, $after);

        return $this->record($tank, $data, $productId, $loss, $net, $before, $after);
    }

    private function saveTank(Tank $tank, string $newVolume): void
    {
        $tank->current_volume = $newVolume;
        $tank->status = match (true) {
            bccomp($newVolume, '0', 3) === 0 => TankStatus::Empty,
            bccomp($newVolume, (string) $tank->capacity, 3) === 0 => TankStatus::Full,
            default => TankStatus::Partial,
        };
        $tank->save();
    }

    private function record(
        Tank $tank,
        MovementData $data,
        int $productId,
        string $loss,
        string $net,
        string $before,
        string $after,
    ): CargoMovement {
        $isDischarge = $data->type === MovementType::Discharge;

        return CargoMovement::create([
            'reference' => $data->reference,
            'type' => $data->type,
            'tank_id' => $tank->id,
            'product_id' => $productId,
            'vessel_id' => $isDischarge ? $data->vesselId : null,
            'truck_id' => $isDischarge ? null : $data->truckId,
            'declared_volume' => $data->declaredVolume,
            'loss_volume' => $loss,
            'net_volume' => $net,
            'occurred_at' => $data->occurredAt,
            'processed_at' => now(),
            'status' => MovementStatus::Applied,
            'volume_before' => $before,
            'volume_after' => $after,
        ]);
    }
}