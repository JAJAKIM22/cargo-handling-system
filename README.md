# Cargo Handling System

A Laravel + FilamentPHP control dashboard for a bulk liquid port terminal. It tracks palm oil products (CPO, CPOL, PFAD) along a strict pipeline:

**Vessel discharge (inbound) → Shore storage tanks → Truck delivery (outbound)**



- **Live demo:** `https://cargo-handling-system-production-mo5xhv.laravel.cloud/admin`
- **Login:** `admin@gmail.com` / `Admin@2030`
- **Repository:** `https://github.com/JAJAKIM22/cargo-handling-system`




## Highlights

- **Atomic and race-safe:** every movement locks the tank row inside a database transaction; a concurrency test proves two simultaneous discharges cannot claim the same free space.
- **Out-of-order safe:** batches are re-sorted by event timestamp before any balance changes.
- **Explainable:** every rejection is stored with the exact reason, and every ingest run is kept in a batch history.
- **Append-only ledger:** movements cannot be edited or deleted (enforced in code and covered by tests).
- **Operational dashboard:** live tank levels, summary cards, an activity ledger with filters, and downloadable Excel reports.

## Stack

Laravel 13 · PHP 8.3 · FilamentPHP 5 (Livewire) · PostgreSQL · PhpSpreadsheet · PHPUnit

PostgreSQL is required: the design relies on row-level locking and database `CHECK` constraints.

## Quick start

Requirements: PHP 8.3+ with the `intl`, `pdo_pgsql`, `bcmath`, `zip` and `gd` extensions, Composer, PostgreSQL.

```bash
git clone <REPO URL> cargo-handling-system
cd cargo-handling-system
composer install
cp .env.example .env
php artisan key:generate

# create an empty PostgreSQL database, then set DB_CONNECTION=pgsql,
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env

php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000/admin and sign in with the seeded demo user (`admin@example.com` / `password` locally; the live demo uses the credentials above).

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
| `VolumetricLossCalculatorTest` | The 0.5% maths is exact (bcmath) |
| `TankLedgerServiceTest` | Net volume lands after the loss; deliveries have no loss; the tank frees at zero; duplicates are blocked |
| `CapacityOverflowTest` | Overflow throws a domain exception and writes nothing; the DB constraint blocks an overflow too |
| `TankAssignmentRulesTest` | One product and one vessel per loaded tank; a depleted tank accepts anything |
| `OutOfOrderIngestTest` | Out-of-order logs are re-sorted by timestamp; the result is independent of arrival order |
| `ConcurrencyTest` | Two real processes racing for the same free space: exactly one wins |
| `LedgerImmutabilityTest` | Ledger rows cannot be updated or deleted |
| `TerminalReportTest` | The Excel report has the expected sheets and figures |

## Domain rules

| Rule | Implementation |
|---|---|
| **Capacity boundaries.** A tank cannot exceed its capacity. | `TankCapacityExceededException`, plus a Postgres `CHECK (current_volume <= capacity)` as a last line of defence. |
| **Transit loss.** 0.5% mass loss on vessel flows. | Applied to **discharges only**  The net amount (declared minus 0.5%) is what lands in the tank. Single source of truth: `VolumetricLossCalculator`. |
| **Time-series sync.** Logs may arrive out of order. | `ProcessMovementBatch` re-sorts by `occurred_at` (ties by `reference`) **before** any balance changes. |
| **Atomic operations.** No race conditions or duplicate allocation. | Each movement runs in a DB transaction that locks the tank row (`SELECT … FOR UPDATE`). A unique `reference` makes ingestion idempotent. |
| **One cargo per tank**  | A loaded tank holds one product from one vessel. The same product from another vessel is also refused (densities may differ). The tank must be depleted to zero before it accepts a different product or vessel. `TankAssignmentConflictException`. |

## Architecture

Business logic lives in a framework-light domain layer; Filament is only the UI.

```
app/
├── Domain/Cargo/
│   ├── Enums/        ProductType, MovementType, MovementStatus, TankStatus
│   ├── DTOs/         MovementData (validated input), BatchResult
│   ├── Services/     TankLedgerService (locking, rules, balances), VolumetricLossCalculator
│   ├── Actions/      RecordDischarge, RecordDelivery, ProcessMovementBatch
│   └── Exceptions/   CargoException + capacity / stock / assignment / duplicate / not-found / immutable-ledger
├── Models/           Product, Tank, Vessel, Truck, CargoMovement, IngestBatch
├── Observers/        CargoMovementObserver (blocks updates and deletes)
├── Reports/          TerminalReportBuilder (Excel workbook)
└── Filament/
    ├── Resources/    Tanks, Vessels, Trucks, CargoMovements, IngestBatches (+ Products)
    ├── Pages/        IngestMovements, Reports
    └── Widgets/      TerminalStatsWidget (summary cards), TankLevelsWidget (live tank levels)
```

**`CargoMovement` is the ledger.** Every movement, applied or rejected, is stored with its declared, loss and net volumes, the tank balance before and after, `occurred_at` (when it really happened), `processed_at` (when it was applied), who recorded it and which ingest batch it came from. `Tank.current_volume` is the running balance and is changed only by `TankLedgerService`.

**`IngestBatch`** records each run of the Ingest page: rows submitted, applied, rejected (with reasons) and skipped.

## Using the dashboard

- **Dashboard:** summary cards (stored volume, tanks in use, tanks near capacity, discharged and delivered today, rejected today) above live tank levels (product, vessel, volume, fill %, status), refreshing automatically.
- **Cargo Movements:** the read-only ledger with a plain-English activity description, who recorded each entry and its batch. Filter by status, type, tank, batch and date range. **Record discharge** and **Record delivery** open forms that call the domain actions; rule violations appear as notifications with the exact reason.
- **Ingest movements:** paste or upload a JSON array of source logs. The result lists what was applied (in true chronological order), rejected (with reasons) and skipped (duplicates), and links to the saved batch.
- **Batch history:** every ingest run, with the full list of rejected movements and why.
- **Reports:** download an Excel workbook for a date range with four sheets: Summary (per product), Tanks (current snapshot), Movements (the ledger) and Vessels (declared vs landed, transit loss).
- **Products / Tanks / Vessels / Trucks:** reference data. A tank's name and capacity are editable; its volume, product and vessel are read-only because only the ledger may change them. Duplicate codes, IMO numbers and plates are caught by validation with a clear message. Trucks show their delivery count, total delivered and last delivery.

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

3. Open **Batch history** to see the rejections and reasons, then **Cargo Movements** (filter by that batch), then **Reports** to download the workbook.

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

## Deployment

The live demo runs on Laravel Cloud with a managed PostgreSQL database.

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the https URL.
- Migrations and the demo seeder run on deploy (`php artisan migrate --force --seed`).
- The PHP runtime needs `intl`, `pdo_pgsql`, `bcmath`, `zip` and `gd` (Filament and the Excel export require them).

## Assumptions and design decisions

- **Unit:** all quantities are metric tonnes (t), stored as `decimal(14,3)`; maths uses `bcmath`, never floats. Reports convert to numbers for display only.
- **Loss rounding:** the 0.5% loss is truncated (not rounded) to 3 decimals, as `bcmath` does.
- **Duplicates:** a `reference` that already exists is skipped, including a previously rejected one. To retry a rejected movement, the source system sends a new reference.
- **Rejections are kept:** a rejected movement is stored with its failure reason when it can be (known tank and product), and every rejection is also kept in the batch record, so nothing disappears silently.
- **Batch semantics:** each movement is atomic; one rejected movement does not abort the rest of the batch.
- **Ledger immutability:** movements can only be created through the domain actions. The UI has no create, edit or delete for them, and the model observer rejects any update or delete attempted in code. Corrections are new movements.
- **Trucks:** a delivery is recorded when loading completes. Trucks are reusable and may appear in many deliveries; truck scheduling and availability are out of scope. The UI shows each truck's delivery history for information.
- **Seed data:** the demo tanks start with opening balances that have no ledger entries.

## Known limitations

- Re-sorting happens **within a batch**. A late event arriving in a *later* batch than events already applied is applied at its arrival point; it does not trigger a full replay of the tank's history. A replay would need opening balances for pre-loaded tanks.
- No role-based access: any signed-in user can use every screen. Restricting edits to a super admin and adding user management would be the next step.
- Deleting reference data is disabled, because movements reference it.