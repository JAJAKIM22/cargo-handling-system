<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Enums\TankStatus;
use App\Models\Tank;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TankLevelsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tank levels')
            ->query(fn () => Tank::query()->with(['product', 'currentVessel'])->orderBy('code'))
            ->columns([
                TextColumn::make('code')
                    ->label('Tank')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('Empty'),
                TextColumn::make('currentVessel.name')
                    ->label('Vessel')
                    ->placeholder('-'),
                TextColumn::make('current_volume')
                    ->label('Volume (t)')
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd(),
                TextColumn::make('capacity')
                    ->label('Capacity (t)')
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd(),
                TextColumn::make('fill')
                    ->label('Fill')
                    ->state(fn (Tank $record): string => number_format(self::fillPercent($record), 1).'%')
                    ->badge()
                    ->color(fn (Tank $record): string => match (true) {
                        self::fillPercent($record) >= 90 => 'danger',
                        self::fillPercent($record) >= 60 => 'warning',
                        self::fillPercent($record) > 0 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof TankStatus ? $state->value : $state) {
                        'full' => 'danger',
                        'partial' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->paginated(false)
            ->poll('5s');
    }

    // Display-only maths (the ledger itself never uses floats).
    private static function fillPercent(Tank $tank): float
    {
        $capacity = (float) $tank->capacity;

        return $capacity > 0 ? ((float) $tank->current_volume / $capacity) * 100 : 0.0;
    }
}