<?php

namespace Tests\Feature;

use App\Domain\Cargo\Enums\TankStatus;
use App\Domain\Cargo\Exceptions\TankCapacityExceededException;
use App\Domain\Cargo\Services\TankLedgerService;
use App\Models\CargoMovement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

class CapacityOverflowTest extends TestCase
{
    use BuildsCargoFixtures;
    use RefreshDatabase;

    public function test_discharge_beyond_capacity_throws_and_leaves_the_tank_untouched(): void
    {
        $tank = $this->tank(capacity: '1000');

        try {
            // 1006 declared -> 1000.970 net, which is more than the 1000 capacity
            app(TankLedgerService::class)
                ->apply($this->dischargeData($tank, $this->product(), $this->vessel(), '1006'));
            $this->fail('Expected TankCapacityExceededException');
        } catch (TankCapacityExceededException) {
            // expected
        }

        $tank->refresh();
        $this->assertSame('0.000', $tank->current_volume);
        $this->assertNull($tank->product_id);
        $this->assertSame(0, CargoMovement::count());
    }

    public function test_the_overflow_check_uses_the_net_volume_after_loss(): void
    {
        $tank = $this->tank(capacity: '1000');

        // 1005.025 declared -> loss 5.025 -> net exactly 1000.000: fits, so it must be allowed
        app(TankLedgerService::class)
            ->apply($this->dischargeData($tank, $this->product(), $this->vessel(), '1005.025'));

        $tank->refresh();
        $this->assertSame('1000.000', $tank->current_volume);
        $this->assertSame(TankStatus::Full, $tank->status);
    }

    public function test_a_full_tank_rejects_any_further_discharge(): void
    {
        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(volume: '1000', product: $product, vessel: $vessel);

        $this->expectException(TankCapacityExceededException::class);

        app(TankLedgerService::class)
            ->apply($this->dischargeData($tank, $product, $vessel, '10'));
    }

    public function test_the_database_itself_refuses_an_overflow(): void
    {
        $tank = $this->tank(capacity: '1000');

        $this->expectException(QueryException::class);

        // Bypass the domain layer entirely: the CHECK constraint is the last line of defence.
        $tank->newQuery()->whereKey($tank->id)->update(['current_volume' => '1001']);
    }
}