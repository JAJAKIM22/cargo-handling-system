<?php

namespace App\Filament\Resources\Trucks\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrucksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['movements as trips_count' => fn ($q) => $q->where('status', 'applied')])
                ->withSum(['movements as delivered_total' => fn ($q) => $q->where('status', 'applied')], 'net_volume')
                ->withMax(['movements as last_delivery_at' => fn ($q) => $q->where('status', 'applied')], 'occurred_at'))
            ->columns([
                TextColumn::make('plate_number')
                    ->searchable(),
                TextColumn::make('driver_name')
                    ->searchable(),
                TextColumn::make('transporter')
                    ->searchable(),
                TextColumn::make('trips_count')
                    ->label('Deliveries')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('delivered_total')
                    ->label('Total delivered (t)')
                    ->numeric(decimalPlaces: 3)
                    ->placeholder('-')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_delivery_at')
                    ->label('Last delivery')
                    ->dateTime()
                    ->placeholder('Never')
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
                // no bulk delete: trucks referenced by movements cannot be removed
            ]);
    }
}