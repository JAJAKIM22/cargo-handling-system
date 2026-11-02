<?php

namespace App\Filament\Resources\CargoMovements;

use App\Filament\Resources\CargoMovements\Pages\CreateCargoMovement;
use App\Filament\Resources\CargoMovements\Pages\EditCargoMovement;
use App\Filament\Resources\CargoMovements\Pages\ListCargoMovements;
use App\Filament\Resources\CargoMovements\Schemas\CargoMovementForm;
use App\Filament\Resources\CargoMovements\Tables\CargoMovementsTable;
use App\Models\CargoMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CargoMovementResource extends Resource
{
    protected static ?string $model = CargoMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return CargoMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CargoMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCargoMovements::route('/'),
            'create' => CreateCargoMovement::route('/create'),
            'edit' => EditCargoMovement::route('/{record}/edit'),
        ];
    }
}
