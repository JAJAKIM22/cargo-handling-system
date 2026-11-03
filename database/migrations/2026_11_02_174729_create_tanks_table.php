<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                 // e.g. TK-01
            $table->string('name')->nullable();

            // Business rule: a tank holds ONE product from ONE vessel until it is depleted to zero.
            // Both are NULL while the tank is empty; the first discharge assigns them,
            // and the ledger clears them when the volume returns to zero.
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('current_vessel_id')->nullable()->constrained('vessels')->restrictOnDelete();

            // Volumes: decimal, never float. Unit assumption: tonnes (document in README).
            $table->decimal('capacity', 14, 3);
            $table->decimal('current_volume', 14, 3)->default(0);

            // empty | partial | full  (kept in sync by the ledger service)
            $table->string('status', 20)->default('empty');

            $table->timestamps();

            $table->index('product_id');
            $table->index('current_vessel_id');
        });

        // Last line of defence: the DB refuses an overflow even if application code has a bug.
        DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_capacity_positive CHECK (capacity > 0)');
        DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_volume_within_capacity CHECK (current_volume >= 0 AND current_volume <= capacity)');

        // A tank holding cargo must know which product and which vessel it holds.
        DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_loaded_has_product_and_vessel CHECK (
            current_volume = 0 OR (product_id IS NOT NULL AND current_vessel_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};