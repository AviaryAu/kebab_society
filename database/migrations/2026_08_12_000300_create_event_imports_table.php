<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingest_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingest_run_id')->nullable()->constrained()->nullOnDelete();

            $table->string('external_id');
            $table->text('source_url')->nullable();

            // Identity of the underlying happening, independent of who told us
            // about it: normalised title + venue + start date.
            $table->string('fingerprint', 64)->index();

            // Whatever the source handed over, kept verbatim. This is the only
            // place third-party prose is allowed to live: it feeds the
            // copywriter and the review screen, and is never published.
            $table->json('raw_payload')->nullable();
            $table->json('normalised')->nullable();

            // Denormalised for the review queue, so listing it does not mean
            // decoding a JSON blob per row.
            $table->string('proposed_title')->nullable();
            $table->dateTime('proposed_start')->nullable();
            $table->string('proposed_venue_name')->nullable();
            $table->string('proposed_suburb')->nullable();

            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->float('match_confidence')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->text('message')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // One row per item per source; re-runs update rather than pile up.
            $table->unique(['ingest_source_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_imports');
    }
};
