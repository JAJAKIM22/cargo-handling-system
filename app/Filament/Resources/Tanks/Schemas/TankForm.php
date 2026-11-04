<?php

namespace App\Filament\Resources\Tanks\Schemas;

use App\Domain\Cargo\Enums\TankStatus;
use App\Models\Tank;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ---- Editable: plain tank details ----
                TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),

                TextInput::make('name')
                    ->maxLength(255),

                TextInput::make('capacity')
                    ->label('Capacity (t)')
                    ->numeric()
                    ->required()
                    // A tank can never be resized below the volume it currently holds.
                    ->minValue(fn (?Tank $record): float => max(0.001, (float) ($record?->current_volume ?? 0)))
                    ->helperText('Cannot be set below the volume currently in the tank.'),

                // ---- Read-only: owned by the ledger, shown on edit only ----
                // A new tank always starts empty (DB defaults), so these are hidden on create.
                TextInput::make('current_volume')
                    ->label('Current volume (t)')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->helperText('Changed only by recorded discharges and deliveries.'),

                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->placeholder('Empty')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Select::make('current_vessel_id')
                    ->label('Current vessel')
                    ->relationship('currentVessel', 'name')
                    ->placeholder('-')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Select::make('status')
                    ->options(TankStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}