<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staging an event for review is not the same as skipping it.
     *
     * Without this the run log reported "created 0, skipped 12" for a run that
     * had in fact queued twelve events for a human — which reads as a run that
     * did nothing.
     */
    public function up(): void
    {
        Schema::table('ingest_runs', function (Blueprint $table): void {
            $table->unsignedInteger('items_staged')->default(0)->after('items_updated');
        });
    }

    public function down(): void
    {
        Schema::table('ingest_runs', function (Blueprint $table): void {
            $table->dropColumn('items_staged');
        });
    }
};
