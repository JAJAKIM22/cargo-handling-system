<?php

namespace Tests\Feature;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Enums\TankStatus;
use App\Domain\Cargo\Exceptions\DuplicateMovementException;
use App\Domain\Cargo\Exceptions\InsufficientStockException;
use App\Domain\Cargo\Services\TankLedgerService;
use App\Models\CargoMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

class TankLedgerServiceTest extends TestCase
{
    use BuildsCargoFixtures;
    use RefreshDatabase;

    public function test_discharge_lands_the_net_volume_after_the_half_percent_loss(): void
    {
        $tank = $this->tank(capacity: '5000');
        $product = $this->product();
        $vessel = $this->vessel();

        $movement = app(TankLedgerService::class)
            ->apply($this->dischargeData($tank, $product, $vessel, '1000'));

        $this->assertSame('5.000', $movement->loss_volume);
        $this->assertSame('995.000', $movement->net_volume);
        $this->assertSame('0.000', $movement->volume_before);
        $this->assertSame('995.000', $movement->volume_after);
        $this->assertSame(MovementStatus::Applied, $movement->status);

        $tank->refresh();
        $this->assertSame('995.000', $tank->current_volume);
        $this->assertSame($product->id, $tank->product_id);
        $this->assertSame($vessel->id, $tank->current_vessel_id);
        $this->assertSame(TankStatus::Partial, $tank->status);
    }

    public function test_delivery_has_no_loss_and_frees_the_tank_when_it_reaches_zero(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(volume: '100', product: $product, vessel: $vessel);

        $movement = app(TankLedgerService::class)
            ->apply($this->deliveryData($tank, $this->truck(), '100'));

        $this->assertSame('0.000', $movement->loss_volume);
        $this->assertSame('100.000', $movement->net_volume);

        $tank->refresh();
        $this->assertSame('0.000', $tank->current_volume);
        $this->assertSame(TankStatus::Empty, $tank->status);
        $this->assertNull($tank->product_id);
        $this->assertNull($tank->current_vessel_id);
    }

    public function test_delivery_larger_than_stock_is_rejected_and_changes_nothing(): void
    {
        $tank = $this->tank(volume: '50', product: $this->product(), vessel: $this->vessel());

        try {
            app(TankLedgerService::class)->apply($this->deliveryData($tank, $this->truck(), '80'));
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertSame('50.000', $tank->fresh()->current_volume);
        $this->assertSame(0, CargoMovement::count());
    }

    public function test_the_same_reference_cannot_be_applied_twice(): void
    {
        $tank = $this->tank(capacity: '5000');
        $product = $this->product();
        $vessel = $this->vessel();
        $service = app(TankLedgerService::class);

        $service->apply($this->dischargeData($tank, $product, $vessel, '100', reference: 'REF-1'));

        $this->expectException(DuplicateMovementException::class);

        try {
            $service->apply($this->dischargeData($tank, $product, $vessel, '100', reference: 'REF-1'));
        } finally {
            $this->assertSame(1, CargoMovement::count());
            $this->assertSame('99.500', $tank->fresh()->current_volume);
        }
    }
}