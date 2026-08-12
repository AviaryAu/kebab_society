<?php

declare(strict_types=1);

namespace App\Jobs\Ingest;

use App\Models\IngestRun;
use App\Models\IngestSource;
use App\Services\Ingest\EventDraft;
use App\Services\Ingest\EventImporter;
use App\Services\Ingest\SourceRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Runs one source on the queue.
 *
 * A crawl spends most of its time waiting politely between requests, which
 * makes it far too slow for a web request to sit through — the admin "Run now"
 * button dispatches this instead of blocking until the crawl finishes.
 */
class RunSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** A long crawl is normal; a stuck one is not. */
    public int $timeout = 900;

    public function __construct(
        public readonly int $sourceId,
        public readonly ?int $limit = null,
        public readonly bool $dryRun = false,
    ) {
        $this->onQueue('ingest');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // Never let two crawls of the same publisher overlap, however the run
        // was triggered.
        return [(new WithoutOverlapping((string) $this->sourceId))->dontRelease()];
    }

    public function handle(SourceRunner $runner, EventImporter $importer): void
    {
        $source = IngestSource::query()->find($this->sourceId);

        if ($source === null) {
            return;
        }

        $runner->run(
            $source,
            fn (EventDraft $draft, IngestRun $run): string => $this->dryRun
                ? 'skipped'
                : $importer->import($source, $draft, $run),
            $this->dryRun,
            $this->limit,
        );
    }
}
