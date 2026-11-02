<?php

namespace App\Observers;

use App\Models\CargoMovement;

class CargoMovementObserver
{
    /**
     * Handle the CargoMovement "created" event.
     */
    public function created(CargoMovement $cargoMovement): void
    {
        //
    }

    /**
     * Handle the CargoMovement "updated" event.
     */
    public function updated(CargoMovement $cargoMovement): void
    {
        //
    }

    /**
     * Handle the CargoMovement "deleted" event.
     */
    public function deleted(CargoMovement $cargoMovement): void
    {
        //
    }

    /**
     * Handle the CargoMovement "restored" event.
     */
    public function restored(CargoMovement $cargoMovement): void
    {
        //
    }

    /**
     * Handle the CargoMovement "force deleted" event.
     */
    public function forceDeleted(CargoMovement $cargoMovement): void
    {
        //
    }
}
