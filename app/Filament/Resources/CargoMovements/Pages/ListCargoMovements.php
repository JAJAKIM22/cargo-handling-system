<?php

namespace App\Filament\Resources\CargoMovements\Pages;

use App\Domain\Cargo\Actions\RecordDelivery;
use App\Domain\Cargo\Actions\RecordDischarge;
use App\Domain\Cargo\Exceptions\CargoException;
use App\Filament\Resources\CargoMovements\CargoMovementResource;
use App\Models\Product;
use App\Models\Tank;
use App\Models\Truck;
use App\Models\Vessel;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ListCargoMovements extends ListRecords
{
    protected static string $resource = CargoMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->recordDischargeAction(),
            $this->recordDeliveryAction(),
        ];
    }

    /** "TK-01: Crude Palm Oil · MV Palm Voyager (3200.000 of 5000.000 t)" */
    private static function tankOptions(): array
    {
        return Tank::query()
            ->with(['product', 'currentVessel'])
            ->orderBy('code')
            ->get()
            ->mapWithKeys(function (Tank $t): array {
                $cargo = $t->product
                    ? $t->product->name.' · '.($t->currentVessel?->name ?? 'unknown vessel')
                    : 'empty';

                return [$t->id => "{$t->code}: {$cargo} ({$t->current_volume} of {$t->capacity} t)"];
            })
            ->all();
    }

    /** "KDA 101A (last delivery 06 Nov 11:00)": information only, not an availability rule. */
    private static function truckOptions(): array
    {
        return Truck::query()
            ->withMax(['movements as last_delivery_at' => fn ($q) => $q->where('status', 'applied')], 'occurred_at')
            ->orderBy('plate_number')
            ->get()
            ->mapWithKeys(fn (Truck $t): array => [
                $t->id => $t->plate_number.($t->last_delivery_at
                    ? ' (last delivery '.Carbon::parse($t->last_delivery_at)->format('d M H:i').')'
                    : ' (no deliveries yet)'),
            ])
            ->all();
    }

    private function recordDischargeAction(): Action
    {
        return Action::make('recordDischarge')
            ->label('Record discharge')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('primary')
            ->modalHeading('Record vessel discharge')
            ->modalDescription('Inbound cargo. A 0.5% transit loss is deducted; the net volume lands in the tank.')
            ->schema([
                TextInput::make('reference')
                    ->required()
                    ->default(fn (): string => 'DIS-'.Str::upper(Str::random(8))),
                Select::make('tank_id')
                    ->label('Tank')
                    ->options(fn (): array => self::tankOptions())
                    ->searchable()
                    ->required(),
                Select::make('product_id')
                    ->label('Product')
                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required(),
                Select::make('vessel_id')
                    ->label('Vessel')
                    ->options(fn (): array => Vessel::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('declared_volume')
                    ->label('Declared volume (t)')
                    ->numeric()
                    ->minValue(0.001)
                    ->required(),
                DateTimePicker::make('occurred_at')
                    ->label('Occurred at')
                    ->default(fn () => now())
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    $movement = app(RecordDischarge::class)->handle([...$data, 'recorded_by' => Auth::id()]);
                } catch (CargoException|InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Discharge rejected')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    $action->halt(); // keep the modal open so the operator can correct the input

                    return;
                }

                Notification::make()
                    ->title('Discharge recorded')
                    ->body("Landed {$movement->net_volume} t (loss {$movement->loss_volume} t). Tank now holds {$movement->volume_after} t.")
                    ->success()
                    ->send();
            });
    }

    private function recordDeliveryAction(): Action
    {
        return Action::make('recordDelivery')
            ->label('Record delivery')
            ->icon('heroicon-m-truck')
            ->color('gray')
            ->modalHeading('Record truck delivery')
            ->modalDescription('Outbound cargo. No transit loss is applied to deliveries.')
            ->schema([
                TextInput::make('reference')
                    ->required()
                    ->default(fn (): string => 'DEL-'.Str::upper(Str::random(8))),
                Select::make('tank_id')
                    ->label('Tank')
                    ->options(fn (): array => self::tankOptions())
                    ->searchable()
                    ->required(),
                Select::make('truck_id')
                    ->label('Truck')
                    ->options(fn (): array => self::truckOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('declared_volume')
                    ->label('Volume to load (t)')
                    ->numeric()
                    ->minValue(0.001)
                    ->required(),
                DateTimePicker::make('occurred_at')
                    ->label('Occurred at')
                    ->default(fn () => now())
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    $movement = app(RecordDelivery::class)->handle([...$data, 'recorded_by' => Auth::id()]);
                } catch (CargoException|InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Delivery rejected')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Delivery recorded')
                    ->body("Loaded {$movement->net_volume} t. Tank now holds {$movement->volume_after} t.")
                    ->success()
                    ->send();
            });
    }
}