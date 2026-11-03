<?php

namespace App\Domain\Cargo\Exceptions;

final class TankNotFoundException extends CargoException
{
    public static function forId(int $id): self
    {
        return new self("Tank #{$id} does not exist.");
    }
}