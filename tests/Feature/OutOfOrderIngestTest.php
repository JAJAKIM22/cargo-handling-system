<?php

namespace Tests\Feature;

use App\Domain\Cargo\Actions\ProcessMovementBatch;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Models\CargoMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

class OutOfOrderIngestTest extends TestCase
{
    use BuildsCargoFixtures;
    use RefreshDatabase;

    public function test_a_delivery_that_arrives_before_the_discharge_feeding_it_is_still_applied(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(volume: '10', product: $product, vessel: $vessel);
        $truck = $this->truck();

        // Arrival order is WRONG: the 14:10 delivery shows up before the 14:02 discharge.
        $result = app(ProcessMovementBatch::class)->handle([
            $this->deliveryData($tank, $truck, '30', '2026-11-03 14:10:00', 'DEL-1'),
            $this->dischargeData($tank, $product, $vessel, '40', '2026-11-03 14:02:00', 'DIS-1'),
        ]);

        $this->assertSame(2, $result->appliedCount());
        $this->assertSame(0, $result->rejectedCount());
        // 10 + (40 - 0.5%) - 30
        $this->assertSame('19.800', $tank->fresh()->current_volume);
    }

    public function test_a_discharge_that_arrives_before_the_delivery_freeing_its_space_is_still_applied(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(capacity: '150', volume: '140', product: $product, vessel: $vessel);
        $truck = $this->truck();

        // In arrival order the discharge would overflow (140 + 39.8 > 150).
        $result = app(ProcessMovementBatch::class)->handle([
            $this->dischargeData($tank, $product, $vessel, '40', '2026-11-03 14:10:00', 'DIS-1'),
            $this->deliveryData($tank, $truck, '30', '2026-11-03 14:02:00', 'DEL-1'),
        ]);

        $this->assertSame(2, $result->appliedCount());
        $this->assertSame(0, $result->rejectedCount());
        $this->assertSame('149.800', $tank->fresh()->current_volume);
    }

    public function test_the_final_balance_is_the_same_for_any_arrival_order(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $truck = $this->truck();
        $inOrder = $this->tank('TK-A', '1000', '10', $product, $vessel);
        $reversed = $this->tank('TK-B', '1000', '10', $product, $vessel);

        $build = fn ($tank, string $suffix) => [
            $this->dischargeData($tank, $product, $vessel, '200', '2026-11-03 08:00:00', "D1-$suffix"),
            $this->deliveryData($tank, $truck, '100', '2026-11-03 09:00:00', "L1-$suffix"),
            $this->dischargeData($tank, $product, $vessel, '100', '2026-11-03 10:00:00', "D2-$suffix"),
            $this->deliveryData($tank, $truck, '150', '2026-11-03 11:00:00', "L2-$suffix"),
        ];

        app(ProcessMovementBatch::class)->handle($build($inOrder, 'A'));
        app(ProcessMovementBatch::class)->handle(array_reverse($build($reversed, 'B')));

        $this->assertSame($inOrder->fresh()->current_volume, $reversed->fresh()->current_volume);
        // 10 + 199 - 100 + 99.5 - 150
        $this->assertSame('58.500', $inOrder->fresh()->current_volume);
    }

    public function test_a_rejected_movement_is_recorded_and_the_batch_carries_on(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(capacity: '100');

        $result = app(ProcessMovementBatch::class)->handle([
            $this->dischargeData($tank, $product, $vessel, '200', '2026-11-03 09:00:00', 'TOO-BIG'),
            $this->dischargeData($tank, $product, $vessel, '50', '2026-11-03 10:00:00', 'FITS'),
        ]);

        $this->assertSame(1, $result->appliedCount());
        $this->assertSame(1, $result->rejectedCount());
        $this->assertSame('TOO-BIG', $result->rejected[0]['reference']);

        $rejected = CargoMovement::where('reference', 'TOO-BIG')->first();
        $this->assertSame(MovementStatus::Rejected, $rejected->status);
        $this->assertNotEmpty($rejected->failure_reason);
        $this->assertSame('49.750', $tank->fresh()->current_volume);
    }

    public function test_duplicate_references_in_a_batch_are_skipped(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(capacity: '1000');

        $result = app(ProcessMovementBatch::class)->handle([
            $this->dischargeData($tank, $product, $vessel, '100', '2026-11-03 09:00:00', 'SAME'),
            $this->dischargeData($tank, $product, $vessel, '100', '2026-11-03 09:00:00', 'SAME'),
        ]);

        $this->assertSame(1, $result->appliedCount());
        $this->assertSame(['SAME'], $result->skipped);
        $this->assertSame('99.500', $tank->fresh()->current_volume);
    }
}