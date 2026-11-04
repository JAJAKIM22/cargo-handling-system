<?php

namespace App\Filament\Resources\CargoMovements\Tables;


use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CargoMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('tank.code')->label('Tank')->sortable(),
                TextColumn::make('product.name')->label('Product'),
                TextColumn::make('vessel.name')->label('Vessel')->placeholder('-'),
                TextColumn::make('truck.plate_number')->label('Truck')->placeholder('-'),
                TextColumn::make('declared_volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('loss_volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('net_volume')->numeric(3)->sortable(),
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('volume_before')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('volume_after')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
