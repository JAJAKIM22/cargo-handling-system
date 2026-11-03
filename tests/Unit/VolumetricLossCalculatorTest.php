<?php

namespace Tests\Unit;

use App\Domain\Cargo\Services\VolumetricLossCalculator;
use PHPUnit\Framework\TestCase;

class VolumetricLossCalculatorTest extends TestCase
{
    public function test_loss_is_half_a_percent_of_declared_volume(): void
    {
        $calculator = new VolumetricLossCalculator();

        $this->assertSame('5.000', $calculator->lossFor('1000'));
        $this->assertSame('200.000', $calculator->lossFor('40000'));
    }

    public function test_net_volume_is_declared_minus_loss(): void
    {
        $calculator = new VolumetricLossCalculator();

        $this->assertSame('995.000', $calculator->netFor('1000'));
        $this->assertSame('39800.000', $calculator->netFor('40000'));
    }

    public function test_fractional_volumes_stay_exact_and_loss_is_truncated_to_three_decimals(): void
    {
        $calculator = new VolumetricLossCalculator();

        // 250.5 * 0.005 = 1.2525 -> truncated to 1.252
        $this->assertSame('1.252', $calculator->lossFor('250.5'));
        $this->assertSame('249.248', $calculator->netFor('250.5'));
    }
}