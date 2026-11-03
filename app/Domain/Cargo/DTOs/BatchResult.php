<?php

namespace App\Domain\Cargo\DTOs;

use App\Models\CargoMovement;

/** Outcome of processing a batch of movements. */
final class BatchResult
{
    /** @var list<CargoMovement> */
    public array $applied = [];

    /** @var list<array{reference: string, reason: string}> */
    public array $rejected = [];

    /** @var list<string> References skipped because they were already recorded. */
    public array $skipped = [];

    public function appliedCount(): int
    {
        return count($this->applied);
    }

    public function rejectedCount(): int
    {
        return count($this->rejected);
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    public function isFullySuccessful(): bool
    {
        return $this->rejected === [];
    }
}