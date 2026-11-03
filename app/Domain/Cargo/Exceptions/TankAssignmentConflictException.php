<?php

namespace App\Domain\Cargo\Exceptions;

use App\Models\Product;
use App\Models\Tank;
use App\Models\Vessel;

/**
 * A non-empty tank holds ONE product from ONE vessel. Anything else would mix cargo
 * (different commodities, or the same commodity with different densities).
 */
final class TankAssignmentConflictException extends CargoException
{
    public static function productMismatch(Tank $tank, int $requestedProductId): self
    {
        $held = $tank->product?->code?->value ?? 'another product';
        $requested = Product::query()->find($requestedProductId)?->code->value ?? "#{$requestedProductId}";

        return new self(sprintf(
            'Tank %s holds %s and cannot receive %s until it is depleted to zero.',
            $tank->code,
            $held,
            $requested,
        ));
    }

    public static function vesselMismatch(Tank $tank, int $requestedVesselId): self
    {
        $held = $tank->currentVessel?->name ?? 'another vessel';
        $requested = Vessel::query()->find($requestedVesselId)?->name ?? "#{$requestedVesselId}";

        return new self(sprintf(
            'Tank %s holds cargo from %s; cargo from %s cannot be mixed in until the tank is empty (cargo densities may differ).',
            $tank->code,
            $held,
            $requested,
        ));
    }
}