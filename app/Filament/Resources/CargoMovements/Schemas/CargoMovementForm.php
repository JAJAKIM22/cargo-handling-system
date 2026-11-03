<?php

namespace App\Filament\Resources\CargoMovements\Schemas;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CargoMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required()
                    ->unique(ignoreRecord: true),

                Select::make('type')
                    ->options(MovementType::class)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set)),

                Select::make('tank_id')
                    ->relationship('tank', 'code')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('vessel_id')
                    ->relationship('vessel', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn(Get $get) => self::isType($get, MovementType::Discharge))
                    ->required(fn(Get $get) => self::isType($get, MovementType::Discharge)),

                Select::make('truck_id')
                    ->relationship('truck', 'plate_number')
                    ->searchable()
                    ->preload()
                    ->visible(fn(Get $get) => self::isType($get, MovementType::Delivery))
                    ->required(fn(Get $get) => self::isType($get, MovementType::Delivery)),
                TextInput::make('declared_volume')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculate($get, $set)),

                TextInput::make('loss_volume')
                    ->numeric()
                    ->default(0)
                    ->readOnly(),

                TextInput::make('net_volume')
                    ->numeric()
                    ->readOnly(),

                DateTimePicker::make('occurred_at')
                    ->required(),

                DateTimePicker::make('processed_at')->disabled(),

                Select::make('status')
                    ->options(MovementStatus::class)
                    ->default(MovementStatus::Pending->value)
                    ->required(),

                Textarea::make('failure_reason')->columnSpanFull(),

                TextInput::make('volume_before')->numeric()->readOnly(),
                TextInput::make('volume_after')->numeric()->readOnly(),
            ]);
    }

    private static function isType(Get $get, MovementType $type): bool
    {
        $state = $get('type');

        return ($state instanceof MovementType ? $state->value : $state) === $type->value;
    }

    private static function recalculate(Get $get, Set $set): void
{
    $declared = (string) ($get('declared_volume') ?: '0');

    $loss = self::isType($get, MovementType::Discharge)
        ? bcmul($declared, '0.005', 3)
        : '0.000';

    $set('loss_volume', $loss);
    $set('net_volume', bcsub($declared, $loss, 3));
}
}
