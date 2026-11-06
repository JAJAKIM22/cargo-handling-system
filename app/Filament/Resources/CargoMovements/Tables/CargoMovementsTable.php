<?php

namespace App\Filament\Resources\CargoMovements\Tables;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use App\Models\IngestBatch;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CargoMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load everything the columns and the readable "summary" need (avoids N+1 queries).
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['tank', 'product', 'vessel', 'truck', 'recorder', 'batch']))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof MovementType ? $state->value : $state) {
                        'discharge' => 'primary',
                        'delivery' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('tank.code')
                    ->label('Tank')
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Product'),
                TextColumn::make('vessel.name')
                    ->label('Vessel')
                    ->placeholder('-'),
                TextColumn::make('truck.plate_number')
                    ->label('Truck')
                    ->placeholder('-'),
                TextColumn::make('declared_volume')
                    ->label('Declared (t)')
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd(),
                TextColumn::make('net_volume')
                    ->label('Net (t)')
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof MovementStatus ? $state->value : $state) {
                        'applied' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('failure_reason')
                    ->label('Reason')
                    ->limit(80)
                    ->tooltip(fn ($state): ?string => $state)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('recorder.name')
                    ->label('Recorded by')
                    ->placeholder('System')
                    ->toggleable(),
                TextColumn::make('batch.id')
                    ->label('Batch')
                    ->prefix('#')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('summary')
                    ->label('Activity')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MovementStatus::class),
                SelectFilter::make('type')
                    ->options(MovementType::class),
                SelectFilter::make('tank_id')
                    ->label('Tank')
                    ->relationship('tank', 'code'),
                SelectFilter::make('ingest_batch_id')
                    ->label('Batch')
                    ->options(fn (): array => IngestBatch::query()->latest()->limit(50)->get()
                        ->mapWithKeys(fn (IngestBatch $b): array => [
                            $b->id => "#{$b->id} · {$b->label} · {$b->created_at->format('d M H:i')}",
                        ])->all()),
                Filter::make('occurred')
                    ->schema([
                        DatePicker::make('from')->label('Occurred from'),
                        DatePicker::make('until')->label('Occurred until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                
            ]);
    }
}