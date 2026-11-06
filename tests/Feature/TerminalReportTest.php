<?php

namespace Tests\Feature;

use App\Domain\Cargo\Actions\ProcessMovementBatch;
use App\Reports\TerminalReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

class TerminalReportTest extends TestCase
{
    use BuildsCargoFixtures;
    use RefreshDatabase;

    public function test_report_contains_the_expected_sheets_and_figures(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $truck = $this->truck();
        $tank = $this->tank(capacity: '5000');
        $day = fn (int $hour): string => now()->startOfDay()->setTime($hour, 0)->toDateTimeString();

        app(ProcessMovementBatch::class)->handle([
            $this->dischargeData($tank, $product, $vessel, '1000', $day(9), 'R-DIS'),
            $this->deliveryData($tank, $truck, '100', $day(10), 'R-DEL'),
            $this->dischargeData($tank, $product, $vessel, '999999', $day(11), 'R-BAD'), // rejected: overflow
        ]);

        $spreadsheet = app(TerminalReportBuilder::class)->build(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(['Summary', 'Tanks', 'Movements', 'Vessels'], $spreadsheet->getSheetNames());

        $summary = $spreadsheet->getSheetByName('Summary')->toArray(null, true, false);
        $row = collect($summary)->first(fn (array $r): bool => ($r[0] ?? null) === $product->name);

        $this->assertNotNull($row);
        $this->assertEquals(995.0, $row[4]);  // net landed: 1000 less 0.5%
        $this->assertEquals(100.0, $row[6]);  // delivered
        $this->assertEquals(895.0, $row[7]);  // net change

        // header + 3 ledger rows (the rejected movement is included, with its reason)
        $movements = $spreadsheet->getSheetByName('Movements')->toArray(null, true, false);
        $this->assertCount(4, $movements);
    }
}