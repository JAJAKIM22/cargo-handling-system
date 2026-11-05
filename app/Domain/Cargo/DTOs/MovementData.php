<?php

namespace App\Domain\Cargo\DTOs;

use App\Domain\Cargo\Enums\MovementType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * One source-log entry, validated and framework-agnostic.
 * Volumes are numeric STRINGS (never floats) so bcmath stays exact.
 */
final readonly class MovementData
{
    public function __construct(
        public string $reference,
        public MovementType $type,
        public int $tankId,
        public string $declaredVolume,
        public CarbonImmutable $occurredAt,
        public ?int $productId = null,
        public ?int $vesselId = null,
        public ?int $truckId = null,
        public ?int $recordedBy = null,
        public ?int $batchId = null,
    ) {
        if (trim($reference) === '') {
            throw new InvalidArgumentException('Reference is required.');
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $declaredVolume) || bccomp($declaredVolume, '0', 3) <= 0) {
            throw new InvalidArgumentException("Declared volume must be a positive number, got '{$declaredVolume}'.");
        }

        if ($type === MovementType::Discharge && ($productId === null || $vesselId === null)) {
            throw new InvalidArgumentException('A discharge requires product_id and vessel_id.');
        }

        if ($type === MovementType::Delivery && $truckId === null) {
            throw new InvalidArgumentException('A delivery requires truck_id.');
        }
    }

    /**
     * Build from a raw row (JSON/CSV/form). Throws InvalidArgumentException on bad input.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        foreach (['reference', 'type', 'tank_id', 'declared_volume', 'occurred_at'] as $key) {
            if (! isset($row[$key]) || $row[$key] === '') {
                throw new InvalidArgumentException("Missing field: {$key}");
            }
        }

        $type = $row['type'] instanceof MovementType
            ? $row['type']
            : MovementType::tryFrom((string) $row['type']);

        if ($type === null) {
            throw new InvalidArgumentException('Unknown movement type: '.$row['type']);
        }

        return new self(
            reference: (string) $row['reference'],
            type: $type,
            tankId: (int) $row['tank_id'],
            declaredVolume: (string) $row['declared_volume'],
            occurredAt: CarbonImmutable::parse($row['occurred_at']),
            productId: self::optionalInt($row['product_id'] ?? null),
            vesselId: self::optionalInt($row['vessel_id'] ?? null),
            truckId: self::optionalInt($row['truck_id'] ?? null),
            recordedBy: self::optionalInt($row['recorded_by'] ?? null),
            batchId: self::optionalInt($row['ingest_batch_id'] ?? null),
        );
    }

    private static function optionalInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}