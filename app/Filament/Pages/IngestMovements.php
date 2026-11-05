<?php

namespace App\Filament\Pages;

use App\Domain\Cargo\Actions\ProcessMovementBatch;
use App\Models\CargoMovement;
use App\Models\IngestBatch;
use App\Models\Product;
use App\Models\Tank;
use App\Models\Truck;
use App\Models\Vessel;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonException;
use Livewire\WithFileUploads;

class IngestMovements extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.ingest-movements';

    public string $json = '';

    /** Livewire temporary upload (JSON file). */
    public $upload = null;

    public string $sourceLabel = 'Pasted JSON';

    public bool $processed = false;

    public ?int $batchId = null;

    /** @var array<int, array<string, string>> In the order they were actually applied. */
    public array $applied = [];

    /** @var array<int, array<string, string>> */
    public array $rejected = [];

    /** @var array<int, array<string, string>> */
    public array $skipped = [];

    public static function getNavigationLabel(): string
    {
        return 'Ingest movements';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Ingest movements';
    }

    public function updatedUpload(): void
    {
        if ($this->upload === null) {
            return;
        }

        $this->json = (string) $this->upload->get();
        $this->sourceLabel = $this->upload->getClientOriginalName();
        $this->upload = null;
    }

    public function batchUrl(): ?string
    {
        $resource = \App\Filament\Resources\IngestBatches\IngestBatchResource::class;

        if ($this->batchId === null || ! class_exists($resource) || ! $resource::hasPage('view')) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $this->batchId]);
    }

    public function process(): void
    {
        $this->reset(['applied', 'rejected', 'skipped', 'processed', 'batchId']);

        if (trim($this->json) === '') {
            $this->warn('Nothing to process', 'Paste a JSON array of movements or upload a .json file.');

            return;
        }

        try {
            $rows = json_decode($this->json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->fail('Invalid JSON', $e->getMessage());

            return;
        }

        if (is_array($rows) && ! array_is_list($rows)) {
            $rows = [$rows]; // a single object is fine
        }

        if (! is_array($rows) || $rows === [] || in_array(false, array_map('is_array', $rows), true)) {
            $this->fail('Invalid payload', 'Expected a JSON array of movement objects.');

            return;
        }

        // Every run is recorded, so rejections can be reviewed later from batch history.
        $batch = IngestBatch::create([
            'user_id' => Auth::id(),
            'label' => $this->sourceLabel,
            'total_rows' => count($rows),
        ]);

        // Our keys come last so a payload cannot spoof who recorded it or which batch it belongs to.
        $rows = array_map(
            fn (array $row): array => [...$row, 'recorded_by' => Auth::id(), 'ingest_batch_id' => $batch->id],
            $rows,
        );

        $result = app(ProcessMovementBatch::class)->handleRows($rows);

        $this->applied = collect($result->applied)
            ->values()
            ->map(fn (CargoMovement $m, int $i): array => [
                'seq' => (string) ($i + 1),
                'reference' => $m->reference,
                'type' => $m->type->label(),
                'tank' => $m->tank->code,
                'occurred_at' => $m->occurred_at->format('Y-m-d H:i'),
                'declared' => (string) $m->declared_volume,
                'loss' => (string) $m->loss_volume,
                'net' => (string) $m->net_volume,
                'balance' => (string) $m->volume_after,
            ])->all();

        $this->rejected = array_map(
            fn (array $r): array => ['reference' => $r['reference'], 'reason' => $r['reason']],
            $result->rejected,
        );

        $this->skipped = array_map(fn (string $ref): array => ['reference' => $ref], $result->skipped);

        $batch->update([
            'applied_count' => $result->appliedCount(),
            'rejected_count' => $result->rejectedCount(),
            'skipped_count' => $result->skippedCount(),
            'rejected' => $this->rejected,
            'skipped' => $this->skipped,
        ]);

        $this->batchId = $batch->id;
        $this->processed = true;

        $summary = sprintf(
            '%d applied, %d rejected, %d skipped (duplicates)',
            $result->appliedCount(),
            $result->rejectedCount(),
            $result->skippedCount(),
        );

        Notification::make()
            ->title("Batch #{$batch->id} processed")
            ->body($summary)
            ->color($result->isFullySuccessful() ? 'success' : 'warning')
            ->icon($result->isFullySuccessful() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
            ->send();
    }

    /**
     * Demo batch that arrives in the WRONG order and contains two deliberate rule violations.
     * Expected: DIS-1 and DEL-1 applied (in time order), DIS-2 and DIS-3 rejected.
     */
    public function loadSample(): void
    {
        $tank = Tank::query()->where('code', 'TK-04')->first();
        $loadedCpoTank = Tank::query()->where('code', 'TK-01')->first();
        $cpol = Product::query()->where('code', 'CPOL')->first();
        $cpo = Product::query()->where('code', 'CPO')->first();
        $oleinStar = Vessel::query()->where('name', 'MV Olein Star')->first();
        $golden = Vessel::query()->where('name', 'MV Golden Horizon')->first();
        $truck = Truck::query()->first();

        if (! $tank || ! $loadedCpoTank || ! $cpol || ! $cpo || ! $oleinStar || ! $golden || ! $truck) {
            $this->warn('Seed data missing', 'Run: php artisan migrate:fresh --seed');

            return;
        }

        $tag = Str::upper(Str::random(4));
        $at = fn (int $h, int $m = 0): string => now()->startOfDay()->setTime($h, $m)->toDateTimeString();

        $rows = [
            ['reference' => "DEMO-{$tag}-DEL-1", 'type' => 'delivery', 'tank_id' => $tank->id,
                'truck_id' => $truck->id, 'declared_volume' => '400', 'occurred_at' => $at(11)],
            ['reference' => "DEMO-{$tag}-DIS-2", 'type' => 'discharge', 'tank_id' => $tank->id,
                'product_id' => $cpol->id, 'vessel_id' => $oleinStar->id, 'declared_volume' => '3000', 'occurred_at' => $at(10)],
            ['reference' => "DEMO-{$tag}-DIS-1", 'type' => 'discharge', 'tank_id' => $tank->id,
                'product_id' => $cpol->id, 'vessel_id' => $oleinStar->id, 'declared_volume' => '1000', 'occurred_at' => $at(9)],
            ['reference' => "DEMO-{$tag}-DIS-3", 'type' => 'discharge', 'tank_id' => $loadedCpoTank->id,
                'product_id' => $cpo->id, 'vessel_id' => $golden->id, 'declared_volume' => '100', 'occurred_at' => $at(10, 30)],
        ];

        $this->json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->sourceLabel = 'Demo batch';
        $this->reset(['applied', 'rejected', 'skipped', 'processed', 'batchId']);
    }

    private function fail(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->danger()->send();
    }

    private function warn(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->warning()->send();
    }
}