<?php

namespace App\Filament\Resources\IngestBatches\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IngestBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Batch')
                    ->prefix('#')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System'),
                TextColumn::make('label')
                    ->label('Source'),
                TextColumn::make('total_rows')
                    ->label('Rows')
                    ->alignEnd(),
                TextColumn::make('applied_count')
                    ->label('Applied')
                    ->badge()
                    ->color('success'),
                TextColumn::make('rejected_count')
                    ->label('Rejected')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('skipped_count')
                    ->label('Skipped')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}