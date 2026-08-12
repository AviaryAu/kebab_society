<?php

declare(strict_types=1);

namespace App\Services\Ingest\Contracts;

use App\Models\IngestSource;
use App\Services\Ingest\EventDraft;

/**
 * Everything a source needs to expose to join the pipeline.
 *
 * Adapters are deliberately thin: fetch raw items, turn one raw item into an
 * EventDraft. Matching, deduplication, geocoding, copywriting and persistence
 * are the pipeline's job, so a new source is usually two short methods.
 */
interface SourceAdapter
{
    /**
     * Pull raw items from the source.
     *
     * Yields rather than returns so a source with thousands of events does not
     * have to be held in memory at once, and so `--limit` can stop the walk
     * early without having paid for the rest.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function fetch(IngestSource $source): iterable;

    /**
     * Turn one raw item into a draft, or null if it is not a usable event.
     *
     * Returning null is normal and not an error: sold-out placeholders, test
     * records and dateless entries all land here.
     *
     * @param  array<string, mixed>  $item
     */
    public function normalise(IngestSource $source, array $item): ?EventDraft;

    /**
     * Number of HTTP requests spent on the last fetch, for the run log.
     */
    public function requestCount(): int;
}
