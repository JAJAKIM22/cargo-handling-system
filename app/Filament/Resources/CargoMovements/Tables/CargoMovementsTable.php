<?php

namespace App\Filament\Resources\CargoMovements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('tank_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('product_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('vessel_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('truck_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('declared_volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('loss_volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('net_volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
