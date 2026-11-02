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

            // A tank holds one product at a time. Reassign only when empty (enforce in the domain layer).
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Volumes: decimal, never float. Unit assumption: tonnes (document in README).
            $table->decimal('capacity', 14, 3);
            $table->decimal('current_volume', 14, 3)->default(0);

            // empty | partial | full  (kept in sync by the ledger service / observer)
            $table->string('status', 20)->default('empty');

            $table->timestamps();

            $table->index('product_id');
        });

        // Last line of defence: even if application code has a bug, the DB refuses an overflow.
        DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_capacity_positive CHECK (capacity > 0)');
        DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_volume_within_capacity CHECK (current_volume >= 0 AND current_volume <= capacity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};