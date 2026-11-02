<?php

namespace App\Filament\Resources\Trucks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TruckForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('plate_number')
                    ->required(),
                TextInput::make('driver_name'),
                TextInput::make('transporter'),
            ]);
    }
}
