<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignId('ingest_source_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->after('ingest_source_id');
            $table->text('source_url')->nullable()->after('external_id');

            // Shown to the reader as "Details via ..." and linked. Attribution
            // is the consideration we offer for the facts we took.
            $table->string('source_name')->nullable()->after('source_url');
            $table->text('source_attribution_url')->nullable()->after('source_name');

            $table->string('fingerprint', 64)->nullable()->after('source_attribution_url')->index();
            $table->timestamp('last_synced_at')->nullable()->after('fingerprint');

            // Set when an administrator edits an ingested event by hand. The
            // importer treats a locked row as read-only, so human work is never
            // silently overwritten by the next sync.
            $table->boolean('import_locked')->default(false)->after('last_synced_at');

            // Copy provenance. `facts_hash` is what makes regeneration cheap:
            // if the underlying facts have not moved, we do not spend a request.
            $table->timestamp('copy_generated_at')->nullable()->after('import_locked');
            $table->string('copy_model', 60)->nullable()->after('copy_generated_at');
            $table->string('facts_hash', 64)->nullable()->after('copy_model');

            $table->string('image_licence', 20)->nullable()->after('image');
            $table->string('image_credit')->nullable()->after('image_licence');
            $table->text('image_source_url')->nullable()->after('image_credit');

            $table->unique(['ingest_source_id', 'external_id']);
        });

        Schema::table('venues', function (Blueprint $table): void {
            $table->foreignId('ingest_source_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->after('ingest_source_id');
            $table->text('source_url')->nullable()->after('external_id');
            $table->string('google_place_id')->nullable()->after('source_url');
            $table->timestamp('last_synced_at')->nullable()->after('google_place_id');
            $table->boolean('import_locked')->default(false)->after('last_synced_at');

            $table->unique(['ingest_source_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['ingest_source_id', 'external_id']);
            $table->dropConstrainedForeignId('ingest_source_id');
            $table->dropColumn([
                'external_id',
                'source_url',
                'source_name',
                'source_attribution_url',
                'fingerprint',
                'last_synced_at',
                'import_locked',
                'copy_generated_at',
                'copy_model',
                'facts_hash',
                'image_licence',
                'image_credit',
                'image_source_url',
            ]);
        });

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropUnique(['ingest_source_id', 'external_id']);
            $table->dropConstrainedForeignId('ingest_source_id');
            $table->dropColumn([
                'external_id',
                'source_url',
                'google_place_id',
                'last_synced_at',
                'import_locked',
            ]);
        });
    }
};
