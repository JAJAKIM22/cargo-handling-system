<?php

namespace App\Observers;

use App\Domain\Cargo\Exceptions\LedgerImmutableException;
use App\Models\CargoMovement;

/**
 * Enforces the append-only ledger at the model level.
 * Inserts are allowed (via TankLedgerService); updates and deletes are not.
 */
class CargoMovementObserver
{
    public function updating(CargoMovement $movement): never
    {
        throw LedgerImmutableException::forUpdate($movement);
    }

    public function deleting(CargoMovement $movement): never
    {
        throw LedgerImmutableException::forDelete($movement);
    }
}