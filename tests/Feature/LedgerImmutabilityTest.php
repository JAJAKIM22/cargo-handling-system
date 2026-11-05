<?php

namespace Tests\Feature;

use App\Domain\Cargo\Exceptions\LedgerImmutableException;
use App\Domain\Cargo\Services\TankLedgerService;
use App\Models\CargoMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

class LedgerImmutabilityTest extends TestCase
{
    use BuildsCargoFixtures;
    use RefreshDatabase;

    private function movement(): CargoMovement
    {
        return app(TankLedgerService::class)->apply(
            $this->dischargeData($this->tank(capacity: '5000'), $this->product(), $this->vessel(), '100'),
        );
    }

    public function test_a_ledger_entry_cannot_be_updated(): void
    {
        $movement = $this->movement();

        $this->expectException(LedgerImmutableException::class);

        $movement->update(['failure_reason' => 'tampered']);
    }

    public function test_a_ledger_entry_cannot_be_deleted(): void
    {
        $movement = $this->movement();

        try {
            $movement->delete();
            $this->fail('Expected LedgerImmutableException');
        } catch (LedgerImmutableException) {
            // expected
        }

        $this->assertSame(1, CargoMovement::count());
    }
}