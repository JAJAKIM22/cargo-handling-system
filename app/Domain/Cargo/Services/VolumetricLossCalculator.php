<?php

namespace App\Domain\Cargo\Services;

/**
 * The single place that knows about the 0.5% transit loss.
 * Applies to vessel DISCHARGES only (confirmed with the client); deliveries are never adjusted.
 *
 * bcmath truncates (does not round): the loss is rounded DOWN to 3 decimals.
 */
final class VolumetricLossCalculator
{
    public const TRANSIT_LOSS_RATE = '0.005';

    public const SCALE = 3;

    public function lossFor(string $declaredVolume): string
    {
        return bcmul($declaredVolume, self::TRANSIT_LOSS_RATE, self::SCALE);
    }

    public function netFor(string $declaredVolume): string
    {
        return bcsub($declaredVolume, $this->lossFor($declaredVolume), self::SCALE);
    }
}