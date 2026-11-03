<?php

namespace App\Domain\Cargo\Actions;

use App\Domain\Cargo\DTOs\BatchResult;
use App\Domain\Cargo\DTOs\MovementData;
use App\Domain\Cargo\Exceptions\CargoException;
use App\Domain\Cargo\Exceptions\DuplicateMovementException;
use App\Domain\Cargo\Services\TankLedgerService;
use InvalidArgumentException;
use Traversable;

/**
 * Ingest a batch of source logs that may arrive asynchronously / out of order.
 *
 *  1. Re-sort by the event's own timestamp (occurred_at), NOT by arrival order.
 *     Ties are broken by reference so the result is deterministic.
 *  2. Apply them one by one; each movement is its own atomic, locked transaction.
 *  3. A rule violation rejects only that movement (and is recorded); the batch carries on.
 */
final class ProcessMovementBatch
{
    public function __construct(private readonly TankLedgerService $ledger)
    {
    }

    /** @param  iterable<MovementData>  $movements */
    public function handle(iterable $movements): BatchResult
    {
        $queue = $movements instanceof Traversable
            ? iterator_to_array($movements, false)
            : array_values($movements);

        // The requirement: re-sort by timestamp BEFORE touching any balance. (usort is stable in PHP 8+.)
        usort($queue, static fn (MovementData $a, MovementData $b): int => ($a->occurredAt <=> $b->occurredAt)
            ?: strcmp($a->reference, $b->reference));

        $result = new BatchResult();

        foreach ($queue as $movement) {
            try {
                $result->applied[] = $this->ledger->apply($movement);
            } catch (DuplicateMovementException) {
                $result->skipped[] = $movement->reference;
            } catch (CargoException $e) {
                $this->ledger->recordRejection($movement, $e);
                $result->rejected[] = ['reference' => $movement->reference, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Same as handle(), but starts from raw rows (decoded JSON / CSV).
     * Malformed rows are reported as rejected instead of aborting the batch.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function handleRows(array $rows): BatchResult
    {
        $valid = [];
        $invalid = [];

        foreach ($rows as $index => $row) {
            try {
                $valid[] = MovementData::fromArray($row);
            } catch (InvalidArgumentException $e) {
                $invalid[] = [
                    'reference' => (string) ($row['reference'] ?? 'row '.($index + 1)),
                    'reason' => 'Invalid row: '.$e->getMessage(),
                ];
            }
        }

        $result = $this->handle($valid);
        array_push($result->rejected, ...$invalid);

        return $result;
    }
}