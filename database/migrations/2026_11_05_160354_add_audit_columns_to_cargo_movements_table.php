<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_movements', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ingest_batch_id')->nullable()->constrained('ingest_batches')->nullOnDelete();

            $table->index('ingest_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ingest_batch_id');
            $table->dropConstrainedForeignId('recorded_by');
        });
    }
};