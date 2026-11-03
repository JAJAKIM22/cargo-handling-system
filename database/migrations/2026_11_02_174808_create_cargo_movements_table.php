<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_movements', function (Blueprint $table) {
            $table->id();

            // Idempotency key from the source system: the same log entry can never be applied twice.
            $table->string('reference')->unique();

            $table->string('type', 20);                          // discharge | delivery

            $table->foreignId('tank_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('vessel_id')->nullable()->constrained()->restrictOnDelete(); // discharge only
            $table->foreignId('truck_id')->nullable()->constrained()->restrictOnDelete();  // delivery only

            // Volumes
            $table->decimal('declared_volume', 14, 3);           // what the source log says
            $table->decimal('loss_volume', 14, 3)->default(0);   // 0.5% transit loss (discharge only)
            $table->decimal('net_volume', 14, 3);                // declared - loss; what hits the tank

            // occurred_at = when it really happened (sort key); processed_at = when we applied it.
            $table->timestampTz('occurred_at', 3);
            $table->timestampTz('processed_at', 3)->nullable();

            // pending | applied | rejected
            $table->string('status', 20)->default('pending');
            $table->text('failure_reason')->nullable();

            // Running balance snapshots: audit trail, and handy for replay/verification.
            $table->decimal('volume_before', 14, 3)->nullable();
            $table->decimal('volume_after', 14, 3)->nullable();

            $table->timestamps();

            $table->index(['tank_id', 'occurred_at']);
            $table->index('status');
            $table->index('occurred_at');
        });

        DB::statement("ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_type_valid CHECK (type IN ('discharge', 'delivery'))");
        DB::statement("ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_status_valid CHECK (status IN ('pending', 'applied', 'rejected'))");
        DB::statement('ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_volumes_valid CHECK (declared_volume > 0 AND loss_volume >= 0 AND net_volume > 0)');

        DB::statement("ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_source_matches_type CHECK (
            (type = 'discharge' AND vessel_id IS NOT NULL AND truck_id IS NULL)
            OR
            (type = 'delivery' AND truck_id IS NOT NULL AND vessel_id IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_movements');
    }
};