<?php

namespace App\Domain\Cargo\Actions;

use App\Domain\Cargo\DTOs\MovementData;
use App\Domain\Cargo\Enums\MovementType;
use App\Domain\Cargo\Services\TankLedgerService;
use App\Models\CargoMovement;
use InvalidArgumentException;

/** Vessel -> tank. Thin wrapper so UI, API and console all share one entry point. */
final class RecordDischarge
{
    public function __construct(private readonly TankLedgerService $ledger)
    {
    }

    /** @param  MovementData|array<string, mixed>  $data */
    public function handle(MovementData|array $data): CargoMovement
    {
        if (is_array($data)) {
            $data = MovementData::fromArray([...$data, 'type' => MovementType::Discharge]);
        }

        if ($data->type !== MovementType::Discharge) {
            throw new InvalidArgumentException('RecordDischarge only handles discharges.');
        }

        return $this->ledger->apply($data);
    }
}