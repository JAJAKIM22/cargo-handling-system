<?php

namespace App\Filament\Resources\Trucks;

use App\Filament\Resources\Trucks\Pages\CreateTruck;
use App\Filament\Resources\Trucks\Pages\EditTruck;
use App\Filament\Resources\Trucks\Pages\ListTrucks;
use App\Filament\Resources\Trucks\Schemas\TruckForm;
use App\Filament\Resources\Trucks\Tables\TrucksTable;
use App\Models\Truck;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TruckResource extends Resource
{
    protected static ?string $model = Truck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'plate_number';

    public static function form(Schema $schema): Schema
    {
        return TruckForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrucksTable::configure($table);
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
            'index' => ListTrucks::route('/'),
            'create' => CreateTruck::route('/create'),
            'edit' => EditTruck::route('/{record}/edit'),
        ];
    }
}
