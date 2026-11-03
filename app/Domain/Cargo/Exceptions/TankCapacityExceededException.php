<?php

namespace App\Domain\Cargo\Exceptions;

use App\Models\Tank;

final class TankCapacityExceededException extends CargoException
{
    public static function forTank(Tank $tank, string $incomingNet): self
    {
        return new self(sprintf(
            'Tank %s cannot accept %s t: capacity is %s t and it currently holds %s t (%s t free).',
            $tank->code,
            $incomingNet,
            $tank->capacity,
            $tank->current_volume,
            $tank->availableCapacity(),
        ));
    }
}