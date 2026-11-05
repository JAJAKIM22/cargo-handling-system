# Cargo Handling System

A Laravel + FilamentPHP control dashboard for a bulk liquid port terminal. It tracks palm oil products (CPO, CPOL, PFAD) along a strict pipeline:

**Vessel discharge (inbound) → Shore storage tanks → Truck delivery (outbound)**



- **Live demo:** `https://cargo-handling-system-production-mo5xhv.laravel.cloud/admin`  (login: `admin@gmail.com` / `password`)
- **Repository:** `https://github.com/JAJAKIM22/cargo-handling-system`

## Stack

Laravel 13 · PHP 8.3 · FilamentPHP 5 (Livewire) · PostgreSQL · PHPUnit

PostgreSQL is required (not optional): the design relies on row-level locking and database `CHECK` constraints.

## Quick start

Requirements: PHP 8.3+ with the `intl`, `pdo_pgsql`, `bcmath`, `zip` and `gd` extensions, Composer, PostgreSQL.

```bash
git clone https://github.com/JAJAKIM22/cargo-handling-system
cd cargo-handling-system
composer install
cp .env.example .env
php artisan key:generate

# create an empty PostgreSQL database, then set DB_CONNECTION=pgsql,
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env

php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000/admin and sign in with the seeded demo user:

| Email | Password |
|---|---|
| `admin@gmail.com` | `password` |

(Change or remove this account before exposing a public environment.)

`php artisan migrate:fresh --seed` resets everything to the demo state.

## Running the tests

The tests need PostgreSQL too (SQLite cannot run the `CHECK` constraints).

```bash
createdb cargo_handling_test        # or: psql -c "CREATE DATABASE cargo_handling_test;"
```

In `phpunit.xml` set:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="cargo_handling_test"/>
```

```bash
php artisan test
```

| Test | What it proves |
|---|---|
| `VolumetricLossCalculatorTest` | 0.5% maths is exact (bcmath) |
| `TankLedgerServiceTest` | Net volume lands after the loss; deliveries have no loss; tank frees at zero; duplicates blocked |
| `CapacityOverflowTest` | Overflow throws a domain exception and writes nothing; DB constraint blocks overflow too |
| `TankAssignmentRulesTest` | One product and one vessel per loaded tank; a depleted tank accepts anything |
| `OutOfOrderIngestTest` | Out-of-order logs are re-sorted by timestamp; result is independent of arrival order |
| `ConcurrencyTest` | Two real processes racing for the same free space: exactly one wins |

## Domain rules

| Rule | Implementation |
|---|---|
| **Capacity boundaries.** A tank cannot exceed its capacity. | `TankCapacityExceededException`, plus a Postgres `CHECK (current_volume <= capacity)` as a last line of defence. |
| **Transit loss.** 0.5% mass loss on vessel flows. | Applied to **discharges only** (confirmed with the client). The net amount (declared − 0.5%) is what lands in the tank. Single source of truth: `VolumetricLossCalculator`. |
| **Time-series sync.** Logs may arrive out of order. | `ProcessMovementBatch` re-sorts by `occurred_at` (ties by `reference`) **before** any balance changes. |
| **Atomic operations.** No race conditions or duplicate allocation. | Each movement runs in a DB transaction that locks the tank row (`SELECT … FOR UPDATE`). A unique `reference` makes ingestion idempotent. |
| **One cargo per tank** (client clarification). | A loaded tank holds one product from one vessel. The same product from another vessel is also refused (densities may differ). The tank must be depleted to zero before it accepts a different product or vessel. `TankAssignmentConflictException`. |

## Architecture

Business logic lives in a framework-light domain layer; Filament is only the UI.

```
app/
├── Domain/Cargo/
│   ├── Enums/        ProductType, MovementType, MovementStatus, TankStatus
│   ├── DTOs/         MovementData (validated input), BatchResult
│   ├── Services/     TankLedgerService (locking, rules, balances), VolumetricLossCalculator
│   ├── Actions/      RecordDischarge, RecordDelivery, ProcessMovementBatch
│   └── Exceptions/   CargoException + capacity / stock / assignment / duplicate / not-found
├── Models/           Product, Tank, Vessel, Truck, CargoMovement
└── Filament/
    ├── Resources/    Tanks, Vessels, Trucks, CargoMovements (+ Products)
    ├── Pages/        IngestMovements
    └── Widgets/      TankLevelsWidget (live tank levels)
```

**`CargoMovement` is the ledger.** Every movement, applied or rejected, is stored with its declared, loss and net volumes, the tank balance before and after, `occurred_at` (when it really happened) and `processed_at` (when it was applied). The ledger is append-only in the UI. `Tank.current_volume` is the running balance and is changed only by `TankLedgerService`.

## Using the dashboard

- **Dashboard:** live tank levels (product, vessel, volume, fill %, status), refreshing every few seconds.
- **Cargo Movements:** the read-only ledger. **Record discharge** and **Record delivery** open forms that call the domain actions; rule violations appear as notifications with the exact reason.
- **Ingest movements:** paste or upload a JSON array of source logs. The result lists what was applied (in true chronological order), rejected (with reasons) and skipped (duplicates).
**Batch History:**: shows more data about batches that were ran from Ingest movements why some were applied or rejected.
- **Product / Tanks / Vessels / Trucks:** reference data.Currently I have disabled creating, editing or deleting of products, products can only be seeded. A tank's name and capacity are editable; its volume, product and vessel are read-only because only the ledger may change them.

### Guided demo (out-of-order ingestion)

1. Open **Ingest movements**, click **Load demo batch**, then **Process batch**.
2. The batch arrives in the wrong order on purpose:

| Reference | Happened at | Result |
|---|---|---|
| DIS-1: discharge 1000 t into TK-04 | 09:00 | Applied, 995.000 t lands (0.5% loss) |
| DIS-2: discharge 3000 t into TK-04 | 10:00 | Rejected: would exceed capacity |
| DIS-3: CPO from a second vessel into TK-01 | 10:30 | Rejected: tank holds another vessel's cargo |
| DEL-1: deliver 400 t from TK-04 | 11:00 | Applied, leaves 595.000 t |

DEL-1 arrives first but happened last; applied in arrival order it would fail on an empty tank.

### Ingest JSON format

```json
[
  {
    "reference": "DIS-001",
    "type": "discharge",
    "tank_id": 2,
    "product_id": 1,
    "vessel_id": 1,
    "declared_volume": "1000",
    "occurred_at": "2026-11-03 09:00:00"
  },
  {
    "reference": "DEL-001",
    "type": "delivery",
    "tank_id": 2,
    "truck_id": 1,
    "declared_volume": "400",
    "occurred_at": "2026-11-03 11:00:00"
  }
]
```

## Assumptions and design decisions

- **Unit:** all quantities are metric tonnes (t), stored as `decimal(14,3)`; maths uses `bcmath`, never floats.
- **Loss rounding:** the 0.5% loss is truncated (not rounded) to 3 decimals, as `bcmath` does.
- **Duplicates:** a `reference` that already exists is skipped, including a previously rejected one. To retry a rejected movement, the source system sends a new reference.
- **Rejections are kept:** a rejected movement is stored with its failure reason when it can be (known tank and product), so nothing disappears silently.
- **Batch semantics:** each movement is atomic; one rejected movement does not abort the rest of the batch.
- **Ledger immutability:** movements can only be created through the domain actions; the UI has no create, edit or delete for them.

## Known limitations

- Re-sorting happens **within a batch**. A late event arriving in a *later* batch than events already applied is applied at its arrival point; it does not trigger a full replay of the tank's history. A replay would need opening balances for pre-loaded tanks.
- No role-based access: any signed-in user can use every screen. Restricting edits to a super admin would be the next step.
- Deleting reference data is disabled, because movements reference it.