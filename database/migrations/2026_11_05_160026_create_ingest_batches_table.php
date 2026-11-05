<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();                      // "Pasted JSON", file name, "Demo batch"
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('rejected')->nullable();                     // [{reference, reason}]
            $table->json('skipped')->nullable();                      // [{reference}]
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_batches');
    }
};