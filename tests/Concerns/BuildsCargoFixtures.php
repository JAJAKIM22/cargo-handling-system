<?php

namespace Tests\Concerns;

use App\Domain\Cargo\DTOs\MovementData;
use App\Domain\Cargo\Enums\MovementType;
use App\Domain\Cargo\Enums\ProductType;
use App\Domain\Cargo\Enums\TankStatus;
use App\Models\Product;
use App\Models\Tank;
use App\Models\Truck;
use App\Models\Vessel;
use Carbon\CarbonImmutable;

trait BuildsCargoFixtures
{
    protected function product(ProductType $type = ProductType::CPO): Product
    {
        return Product::firstOrCreate(['code' => $type->value], ['name' => $type->label()]);
    }

    protected function vessel(string $name = 'MV Test'): Vessel
    {
        return Vessel::create(['name' => $name]);
    }

    protected function truck(string $plate = 'KAA 001A'): Truck
    {
        return Truck::firstOrCreate(['plate_number' => $plate]);
    }

    /** A tank holding cargo must have a product AND a vessel (DB CHECK constraint). */
    protected function tank(
        string $code = 'TK-T1',
        string $capacity = '1000',
        string $volume = '0',
        ?Product $product = null,
        ?Vessel $vessel = null,
    ): Tank {
        return Tank::create([
            'code' => $code,
            'name' => $code,
            'product_id' => $product?->id,
            'current_vessel_id' => $vessel?->id,
            'capacity' => $capacity,
            'current_volume' => $volume,
            'status' => bccomp($volume, '0', 3) === 0 ? TankStatus::Empty : TankStatus::Partial,
        ]);
    }

    protected function dischargeData(
        Tank $tank,
        Product $product,
        Vessel $vessel,
        string $declared,
        string $at = '2026-11-03 14:00:00',
        ?string $reference = null,
    ): MovementData {
        return new MovementData(
            reference: $reference ?? 'DIS-'.uniqid(),
            type: MovementType::Discharge,
            tankId: $tank->id,
            declaredVolume: $declared,
            occurredAt: CarbonImmutable::parse($at),
            productId: $product->id,
            vesselId: $vessel->id,
        );
    }

    protected function deliveryData(
        Tank $tank,
        Truck $truck,
        string $declared,
        string $at = '2026-11-03 14:00:00',
        ?string $reference = null,
    ): MovementData {
        return new MovementData(
            reference: $reference ?? 'DEL-'.uniqid(),
            type: MovementType::Delivery,
            tankId: $tank->id,
            declaredVolume: $declared,
            occurredAt: CarbonImmutable::parse($at),
            truckId: $truck->id,
        );
    }
}