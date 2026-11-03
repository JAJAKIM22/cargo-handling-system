<?php

namespace Tests\Feature;

use App\Domain\Cargo\Enums\ProductType;
use App\Domain\Cargo\Exceptions\TankAssignmentConflictException;
use App\Domain\Cargo\Services\TankLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

class TankAssignmentRulesTest extends TestCase
{
    use BuildsCargoFixtures;
    use RefreshDatabase;

    public function test_a_loaded_tank_rejects_a_different_product(): void
    {
        $vessel = $this->vessel('MV One');
        $tank = $this->tank(volume: '100', product: $this->product(ProductType::CPO), vessel: $vessel);

        $this->expectException(TankAssignmentConflictException::class);

        app(TankLedgerService::class)->apply(
            $this->dischargeData($tank, $this->product(ProductType::CPOL), $vessel, '50'),
        );
    }

    public function test_a_loaded_tank_rejects_the_same_product_from_another_vessel(): void
    {
        $cpo = $this->product(ProductType::CPO);
        $tank = $this->tank(volume: '100', product: $cpo, vessel: $this->vessel('MV One'));

        $this->expectException(TankAssignmentConflictException::class);

        app(TankLedgerService::class)->apply(
            $this->dischargeData($tank, $cpo, $this->vessel('MV Two'), '50'),
        );
    }

    public function test_the_same_product_from_the_same_vessel_can_top_up_the_tank(): void
    {
        $cpo = $this->product(ProductType::CPO);
        $vessel = $this->vessel('MV One');
        $tank = $this->tank(volume: '100', product: $cpo, vessel: $vessel);

        app(TankLedgerService::class)->apply($this->dischargeData($tank, $cpo, $vessel, '200'));

        $this->assertSame('299.000', $tank->fresh()->current_volume);
    }

    public function test_a_depleted_tank_accepts_a_different_product_and_vessel(): void
    {
        $cpo = $this->product(ProductType::CPO);
        $cpol = $this->product(ProductType::CPOL);
        $vesselOne = $this->vessel('MV One');
        $vesselTwo = $this->vessel('MV Two');
        $tank = $this->tank(volume: '100', product: $cpo, vessel: $vesselOne);
        $service = app(TankLedgerService::class);

        $service->apply($this->deliveryData($tank, $this->truck(), '100', '2026-11-03 10:00:00'));
        $service->apply($this->dischargeData($tank, $cpol, $vesselTwo, '500', '2026-11-03 11:00:00'));

        $tank->refresh();
        $this->assertSame($cpol->id, $tank->product_id);
        $this->assertSame($vesselTwo->id, $tank->current_vessel_id);
        $this->assertSame('497.500', $tank->current_volume);
    }
}