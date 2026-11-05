<?php

namespace App\Filament\Resources\IngestBatches\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IngestBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('Processed')
                    ->dateTime(),
                TextEntry::make('user.name')
                    ->label('Processed by')
                    ->placeholder('System'),
                TextEntry::make('label')
                    ->label('Source'),
                TextEntry::make('total_rows')
                    ->label('Rows submitted'),
                TextEntry::make('applied_count')
                    ->label('Applied')
                    ->badge()
                    ->color('success'),
                TextEntry::make('rejected_count')
                    ->label('Rejected')
                    ->badge()
                    ->color('danger'),
                TextEntry::make('skipped_count')
                    ->label('Skipped (duplicates)')
                    ->badge()
                    ->color('gray'),

                RepeatableEntry::make('rejected')
                    ->label('Rejected movements and why')
                    ->schema([
                        TextEntry::make('reference'),
                        TextEntry::make('reason')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => ! empty($record->rejected))
                    ->columnSpanFull(),

                RepeatableEntry::make('skipped')
                    ->label('Skipped: already recorded')
                    ->schema([
                        TextEntry::make('reference'),
                    ])
                    ->visible(fn ($record): bool => ! empty($record->skipped))
                    ->columnSpanFull(),
            ]);
    }
}