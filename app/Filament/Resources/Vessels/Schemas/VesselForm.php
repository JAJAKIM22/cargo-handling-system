<?php

namespace App\Filament\Resources\Vessels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VesselForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('imo_number')
                    ->label('IMO number')
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'A vessel with this IMO number already exists.']),
                TextInput::make('voyage_reference'),
            ]);
    }
}
