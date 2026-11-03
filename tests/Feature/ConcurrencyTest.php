<?php

namespace Tests\Feature;

use App\Domain\Cargo\DTOs\MovementData;
use App\Domain\Cargo\Exceptions\CargoException;
use App\Domain\Cargo\Services\TankLedgerService;
use App\Models\CargoMovement;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;
use Tests\Concerns\BuildsCargoFixtures;
use Tests\TestCase;

/**
 * Two REAL processes try to fill the same tank at the same moment.
 *
 * Uses DatabaseMigrations (not RefreshDatabase) on purpose: RefreshDatabase wraps the test in a
 * transaction, which other processes could not see. Run this against the dedicated test database only.
 */
class ConcurrencyTest extends TestCase
{
    use BuildsCargoFixtures;
    use DatabaseMigrations;

    public function test_two_simultaneous_discharges_cannot_both_claim_the_same_free_space(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Row-level locking needs PostgreSQL.');
        }

        $product = $this->product();
        $vessel = $this->vessel();
        $tank = $this->tank(capacity: '1000');

        $row = fn (string $reference) => [
            'reference' => $reference,
            'type' => 'discharge',
            'tank_id' => $tank->id,
            'product_id' => $product->id,
            'vessel_id' => $vessel->id,
            'declared_volume' => '600',          // net 597.000 each: only ONE fits in 1000
            'occurred_at' => '2026-11-03 14:00:00',
        ];

        $rowA = $row('RACE-A');
        $rowB = $row('RACE-B');

        $results = Concurrency::driver('process')->run([
            fn () => \Tests\Feature\ConcurrencyTest::attempt($rowA),
            fn () => \Tests\Feature\ConcurrencyTest::attempt($rowB),
        ]);

        $outcomes = array_count_values($results);

        $this->assertSame(1, $outcomes['applied'] ?? 0, 'Exactly one discharge may win: '.json_encode($results));
        $this->assertSame(1, $outcomes['TankCapacityExceededException'] ?? 0, json_encode($results));
        $this->assertSame(1, CargoMovement::where('status', 'applied')->count());
        $this->assertSame('597.000', $tank->fresh()->current_volume);
    }

    /** Runs inside a child process. */
    public static function attempt(array $row): string
    {
        try {
            app(TankLedgerService::class)->apply(MovementData::fromArray($row));

            return 'applied';
        } catch (CargoException $e) {
            return class_basename($e);
        }
    }
}