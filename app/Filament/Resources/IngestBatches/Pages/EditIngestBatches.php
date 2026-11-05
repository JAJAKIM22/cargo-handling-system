<?php

namespace App\Filament\Resources\IngestBatches\Pages;

use App\Filament\Resources\IngestBatches\IngestBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIngestBatches extends EditRecord
{
    protected static string $resource = IngestBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
