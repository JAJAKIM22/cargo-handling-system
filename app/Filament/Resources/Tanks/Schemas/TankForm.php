<?php

namespace App\Filament\Resources\Tanks\Schemas;

use App\Domain\Cargo\Enums\TankStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name'),
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),            // no ->required(): a tank can be empty

                Select::make('current_vessel_id')
                    ->label('Current vessel')
                    ->relationship('currentVessel', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric(),
                TextInput::make('current_volume')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('status')->options(TankStatus::class)
            ]);
    }
}
