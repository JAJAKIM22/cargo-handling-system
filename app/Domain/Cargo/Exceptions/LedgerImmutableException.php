<?php

namespace App\Domain\Cargo\Exceptions;

use App\Models\CargoMovement;

/** The ledger is append-only: a correction is a NEW movement, never an edit. */
final class LedgerImmutableException extends CargoException
{
    public static function forUpdate(CargoMovement $movement): self
    {
        return new self("Ledger entry '{$movement->reference}' cannot be modified. Record a new movement to correct it.");
    }

    public static function forDelete(CargoMovement $movement): self
    {
        return new self("Ledger entry '{$movement->reference}' cannot be deleted. The ledger is an audit trail.");
    }
}