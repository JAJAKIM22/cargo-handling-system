<?php

namespace App\Domain\Cargo\Exceptions;

use App\Models\Tank;

final class InsufficientStockException extends CargoException
{
    public static function forTank(Tank $tank, string $requested): self
    {
        return new self(sprintf(
            'Tank %s holds only %s t and cannot deliver %s t.',
            $tank->code,
            $tank->current_volume,
            $requested,
        ));
    }
}