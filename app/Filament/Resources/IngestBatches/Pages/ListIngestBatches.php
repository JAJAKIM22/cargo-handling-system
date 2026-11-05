<?php

namespace App\Filament\Resources\IngestBatches\Pages;

use App\Filament\Resources\IngestBatches\IngestBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIngestBatches extends ListRecords
{
    protected static string $resource = IngestBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
