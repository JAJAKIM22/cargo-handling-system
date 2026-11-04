<?php

namespace App\Filament\Resources\Tanks\Pages;

use App\Filament\Resources\Tanks\TankResource;
use Filament\Resources\Pages\EditRecord;

class EditTank extends EditRecord
{
    protected static string $resource = TankResource::class;

    /**
     * Server-side guard: even a tampered request cannot change ledger-owned fields.
     * Only code, name and capacity may be edited by hand.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset(
            $data['current_volume'],
            $data['product_id'],
            $data['current_vessel_id'],
            $data['status'],
        );

        return $data;
    }
}