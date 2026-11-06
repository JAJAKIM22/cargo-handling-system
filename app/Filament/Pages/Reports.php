<?php

namespace App\Filament\Pages;

use App\Reports\TerminalReportBuilder;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    public static function getNavigationLabel(): string
    {
        return 'Reports';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-document-chart-bar';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Reports';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadReport')
                ->label('Download Excel report')
                ->icon('heroicon-m-arrow-down-tray')
                ->modalHeading('Download Excel report')
                ->modalDescription('Movements are included by the date they occurred.')
                ->modalSubmitActionLabel('Download')
                ->schema([
                    DatePicker::make('from')
                        ->label('From')
                        ->default(fn () => now()->startOfMonth())
                        ->required(),
                    DatePicker::make('until')
                        ->label('Until')
                        ->default(fn () => now())
                        ->required()
                        ->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    $from = Carbon::parse($data['from'])->startOfDay();
                    $until = Carbon::parse($data['until'])->endOfDay();

                    $spreadsheet = app(TerminalReportBuilder::class)->build($from, $until);

                    $filename = sprintf('cargo-report-%s-to-%s.xlsx', $from->format('Ymd'), $until->format('Ymd'));

                    return response()->streamDownload(
                        function () use ($spreadsheet): void {
                            (new Xlsx($spreadsheet))->save('php://output');
                        },
                        $filename,
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    );
                }),
        ];
    }
}