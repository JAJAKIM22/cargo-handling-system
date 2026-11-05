<?php

namespace App\Filament\Resources\IngestBatches;

use App\Filament\Resources\IngestBatches\Pages\ListIngestBatches;
use App\Filament\Resources\IngestBatches\Pages\ViewIngestBatch;
use App\Filament\Resources\IngestBatches\Schemas\IngestBatchInfolist;
use App\Filament\Resources\IngestBatches\Tables\IngestBatchesTable;
use App\Models\IngestBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Read-only history of ingest runs: what was submitted, applied, rejected (and why) or skipped.
 * Batches are created only by the Ingest movements page, never by hand.
 */
class IngestBatchResource extends Resource
{
    protected static ?string $model = IngestBatch::class;

    public static function getNavigationLabel(): string
    {
        return 'Batch history';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getModelLabel(): string
    {
        return 'ingest batch';
    }

    public static function getPluralModelLabel(): string
    {
        return 'ingest batches';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return IngestBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngestBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIngestBatches::route('/'),
            'view' => ViewIngestBatch::route('/{record}'),
        ];
    }
}