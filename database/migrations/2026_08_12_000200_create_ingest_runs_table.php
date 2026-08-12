<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingest_source_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running')->index();

            $table->unsignedInteger('items_seen')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->unsignedInteger('requests_made')->default(0);

            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('dry_run')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_runs');
    }
};
