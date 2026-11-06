<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use App\Models\CargoMovement;
use App\Models\Tank;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Summary cards: the state of the tank farm right now, and what happened today.
 * Read-only; it never touches the ledger. Display maths may use floats.
 */
class TerminalStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1; // cards first, tank table below

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $capacity = (float) Tank::query()->sum('capacity');
        $stored = (float) Tank::query()->sum('current_volume');
        $fillPercent = $capacity > 0 ? ($stored / $capacity) * 100 : 0.0;

        $totalTanks = Tank::query()->count();
        $emptyTanks = Tank::query()->where('current_volume', 0)->count();
        $nearFull = Tank::query()->whereRaw('capacity > 0 AND current_volume / capacity >= 0.9')->count();

        $from = now()->startOfDay();
        $to = now()->endOfDay();

        $netToday = fn (MovementType $type): float => (float) CargoMovement::query()
            ->where('status', MovementStatus::Applied->value)
            ->where('type', $type->value)
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('net_volume');

        $rejectedToday = CargoMovement::query()
            ->where('status', MovementStatus::Rejected->value)
            ->whereBetween('processed_at', [$from, $to])
            ->count();

        return [
            Stat::make('Stored now', number_format($stored, 1).' t')
                ->description(number_format($fillPercent, 1).'% of '.number_format($capacity, 0).' t capacity')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),

            Stat::make('Tanks in use', ($totalTanks - $emptyTanks).' / '.$totalTanks)
                ->description($emptyTanks.' empty and available')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('gray'),

            Stat::make('Near capacity', (string) $nearFull)
                ->description('tanks 90% full or more')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($nearFull > 0 ? 'warning' : 'gray'),

            Stat::make('Discharged today', number_format($netToday(MovementType::Discharge), 1).' t')
                ->description('landed, after 0.5% transit loss')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Delivered today', number_format($netToday(MovementType::Delivery), 1).' t')
                ->description('loaded onto trucks')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Rejected today', (string) $rejectedToday)
                ->description('see Batch history for reasons')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($rejectedToday > 0 ? 'danger' : 'gray'),
        ];
    }
}