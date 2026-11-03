<?php

namespace App\Domain\Cargo\Exceptions;

final class DuplicateMovementException extends CargoException
{
    public static function forReference(string $reference): self
    {
        return new self("Movement '{$reference}' has already been recorded.");
    }
}