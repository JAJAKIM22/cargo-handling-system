<?php

namespace App\Domain\Cargo\Actions;

use App\Domain\Cargo\DTOs\MovementData;
use App\Domain\Cargo\Enums\MovementType;
use App\Domain\Cargo\Services\TankLedgerService;
use App\Models\CargoMovement;
use InvalidArgumentException;

/** Tank -> truck. */
final class RecordDelivery
{
    public function __construct(private readonly TankLedgerService $ledger)
    {
    }

    /** @param  MovementData|array<string, mixed>  $data */
    public function handle(MovementData|array $data): CargoMovement
    {
        if (is_array($data)) {
            $data = MovementData::fromArray([...$data, 'type' => MovementType::Delivery]);
        }

        if ($data->type !== MovementType::Delivery) {
            throw new InvalidArgumentException('RecordDelivery only handles deliveries.');
        }

        return $this->ledger->apply($data);
    }
}