<?php

namespace App\Reports;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\MovementType;
use App\Models\CargoMovement;
use App\Models\Product;
use App\Models\Tank;
use App\Models\Vessel;
use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the Excel terminal report: Summary (per product), Tanks (snapshot), Movements (ledger), Vessels.
 *
 * Read-only. Report maths use floats for display; the ledger itself never does.
 */
final class TerminalReportBuilder
{
    private const NUMBER_FORMAT = '#,##0.000';

    public function build(CarbonInterface $from, CarbonInterface $until): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->summarySheet($spreadsheet->getActiveSheet(), $from, $until);
        $this->tanksSheet($spreadsheet->createSheet());
        $this->movementsSheet($spreadsheet->createSheet(), $from, $until);
        $this->vesselsSheet($spreadsheet->createSheet(), $from, $until);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function summarySheet(Worksheet $sheet, CarbonInterface $from, CarbonInterface $until): void
    {
        $sheet->setTitle('Summary');
        $sheet->setCellValue('A1', 'Cargo Handling Report');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Period: '.$from->format('Y-m-d').' to '.$until->format('Y-m-d'));
        $sheet->setCellValue('A3', 'Generated: '.now()->format('Y-m-d H:i'));
        $sheet->setCellValue('A4', 'Volumes in metric tonnes (t). Applied movements only. The 0.5% transit loss applies to vessel discharges.');

        $headers = ['Product', 'Discharges', 'Declared (t)', 'Transit loss (t)', 'Net landed (t)', 'Deliveries', 'Delivered (t)', 'Net change (t)'];
        $headerRow = 6;
        $sheet->fromArray($headers, null, 'A'.$headerRow);
        $this->styleHeader($sheet, $headerRow, count($headers));

        $aggregates = CargoMovement::query()
            ->where('status', MovementStatus::Applied->value)
            ->whereBetween('occurred_at', [$from, $until])
            ->selectRaw('product_id, type, COUNT(*) AS movement_count, SUM(declared_volume) AS declared, SUM(loss_volume) AS loss, SUM(net_volume) AS net')
            ->groupBy('product_id', 'type')
            ->get()
            ->groupBy('product_id');

        $rows = [];
        foreach (Product::query()->orderBy('code')->get() as $product) {
            $group = $aggregates->get($product->id, collect());
            $in = $group->first(fn ($r) => $r->type === MovementType::Discharge);
            $out = $group->first(fn ($r) => $r->type === MovementType::Delivery);

            $rows[] = [
                $this->text($product->name),
                (int) ($in?->movement_count ?? 0),
                (float) ($in?->declared ?? 0),
                (float) ($in?->loss ?? 0),
                (float) ($in?->net ?? 0),
                (int) ($out?->movement_count ?? 0),
                (float) ($out?->net ?? 0),
                (float) bcsub((string) ($in?->net ?? '0'), (string) ($out?->net ?? '0'), 3),
            ];
        }

        $first = $headerRow + 1;
        $sheet->fromArray($rows, null, 'A'.$first);
        $last = $headerRow + count($rows);

        $totalRow = $last + 1;
        $totals = ['Total'];
        foreach (range(1, 7) as $col) {
            $totals[] = array_sum(array_column($rows, $col));
        }
        $sheet->fromArray($totals, null, 'A'.$totalRow);
        $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()->setBold(true);

        $this->numberFormat($sheet, 'C', 'E', $first, $totalRow);
        $this->numberFormat($sheet, 'G', 'H', $first, $totalRow);

        $rejected = CargoMovement::query()
            ->where('status', MovementStatus::Rejected->value)
            ->whereBetween('occurred_at', [$from, $until])
            ->count();
        $sheet->setCellValue('A'.($totalRow + 2), 'Rejected movements in period: '.$rejected);

        $this->finish($sheet, count($headers), $headerRow);
    }

    private function tanksSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Tanks');
        $headers = ['Tank', 'Name', 'Product', 'Vessel', 'Capacity (t)', 'Volume (t)', 'Free (t)', 'Fill %', 'Status'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 1, count($headers));

        $rows = [];
        foreach (Tank::query()->with(['product', 'currentVessel'])->orderBy('code')->get() as $tank) {
            $capacity = (float) $tank->capacity;
            $rows[] = [
                $this->text($tank->code),
                $this->text($tank->name),
                $tank->product ? $this->text($tank->product->name) : 'Empty',
                $this->text($tank->currentVessel?->name),
                $capacity,
                (float) $tank->current_volume,
                (float) $tank->availableCapacity(),
                $capacity > 0 ? round(((float) $tank->current_volume / $capacity) * 100, 1) : 0.0,
                $tank->status->label(),
            ];
        }

        $sheet->fromArray($rows, null, 'A2');
        $this->numberFormat($sheet, 'E', 'G', 2, count($rows) + 1);
        $this->finish($sheet, count($headers), 1);
    }

    private function movementsSheet(Worksheet $sheet, CarbonInterface $from, CarbonInterface $until): void
    {
        $sheet->setTitle('Movements');
        $headers = [
            'Occurred at', 'Reference', 'Type', 'Status', 'Tank', 'Product', 'Vessel', 'Truck',
            'Declared (t)', 'Loss (t)', 'Net (t)', 'Balance before (t)', 'Balance after (t)',
            'Reason', 'Recorded by', 'Batch',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 1, count($headers));

        $rows = [];
        CargoMovement::query()
            ->with(['tank', 'product', 'vessel', 'truck', 'recorder'])
            ->whereBetween('occurred_at', [$from, $until])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->each(function (CargoMovement $m) use (&$rows): void {
                $rows[] = [
                    $m->occurred_at->format('Y-m-d H:i:s'),
                    $this->text($m->reference),
                    $m->type->label(),
                    $m->status->label(),
                    $this->text($m->tank?->code),
                    $this->text($m->product?->name),
                    $this->text($m->vessel?->name),
                    $this->text($m->truck?->plate_number),
                    (float) $m->declared_volume,
                    (float) $m->loss_volume,
                    (float) $m->net_volume,
                    $m->volume_before !== null ? (float) $m->volume_before : null,
                    $m->volume_after !== null ? (float) $m->volume_after : null,
                    $this->text($m->failure_reason),
                    $this->text($m->recorder?->name),
                    $m->ingest_batch_id,
                ];
            });

        $sheet->fromArray($rows, null, 'A2');
        $this->numberFormat($sheet, 'I', 'M', 2, count($rows) + 1);
        $this->finish($sheet, count($headers), 1);
    }

    private function vesselsSheet(Worksheet $sheet, CarbonInterface $from, CarbonInterface $until): void
    {
        $sheet->setTitle('Vessels');
        $headers = ['Vessel', 'IMO', 'Discharges', 'Declared (t)', 'Transit loss (t)', 'Net landed (t)', 'Loss %'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 1, count($headers));

        $aggregates = CargoMovement::query()
            ->where('status', MovementStatus::Applied->value)
            ->where('type', MovementType::Discharge->value)
            ->whereBetween('occurred_at', [$from, $until])
            ->selectRaw('vessel_id, COUNT(*) AS movement_count, SUM(declared_volume) AS declared, SUM(loss_volume) AS loss, SUM(net_volume) AS net')
            ->groupBy('vessel_id')
            ->get();

        $vessels = Vessel::query()->whereIn('id', $aggregates->pluck('vessel_id'))->get()->keyBy('id');

        $rows = [];
        foreach ($aggregates as $row) {
            $vessel = $vessels->get($row->vessel_id);
            $declared = (float) $row->declared;
            $rows[] = [
                $this->text($vessel?->name),
                $this->text($vessel?->imo_number),
                (int) $row->movement_count,
                $declared,
                (float) $row->loss,
                (float) $row->net,
                $declared > 0 ? round(((float) $row->loss / $declared) * 100, 2) : 0.0,
            ];
        }

        $sheet->fromArray($rows, null, 'A2');
        $this->numberFormat($sheet, 'D', 'F', 2, count($rows) + 1);
        $this->finish($sheet, count($headers), 1);
    }

    private function styleHeader(Worksheet $sheet, int $row, int $columns): void
    {
        $range = 'A'.$row.':'.Coordinate::stringFromColumnIndex($columns).$row;
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setRGB('E5E7EB');
    }

    private function numberFormat(Worksheet $sheet, string $fromCol, string $toCol, int $fromRow, int $toRow): void
    {
        if ($toRow < $fromRow) {
            return;
        }

        $sheet->getStyle("{$fromCol}{$fromRow}:{$toCol}{$toRow}")
            ->getNumberFormat()
            ->setFormatCode(self::NUMBER_FORMAT);
    }

    private function finish(Worksheet $sheet, int $columns, int $headerRow): void
    {
        foreach (range(1, $columns) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $sheet->freezePane('A'.($headerRow + 1));
    }

    /** Guards against spreadsheet formula injection from user-entered text (=, +, -, @ prefixes). */
    private function text(?string $value): string
    {
        $value ??= '';

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}