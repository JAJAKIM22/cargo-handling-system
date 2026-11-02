<?php

namespace App\Filament\Resources\CargoMovements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CargoMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('tank_id')
                    ->required()
                    ->numeric(),
                TextInput::make('product_id')
                    ->required()
                    ->numeric(),
                TextInput::make('vessel_id')
                    ->numeric(),
                TextInput::make('truck_id')
                    ->numeric(),
                TextInput::make('declared_volume')
                    ->required()
                    ->numeric(),
                TextInput::make('loss_volume')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('net_volume')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('occurred_at')
                    ->required(),
                DateTimePicker::make('processed_at'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('failure_reason')
                    ->columnSpanFull(),
                TextInput::make('volume_before')
                    ->numeric(),
                TextInput::make('volume_after')
                    ->numeric(),
            ]);
    }
}
