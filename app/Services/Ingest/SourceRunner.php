<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Models\IngestRun;
use App\Models\IngestSource;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one source end to end and keeps the books.
 *
 * The handler callback is what varies: a dry run prints, a real run persists.
 * Everything around it -- the run record, the counters, the failure handling,
 * the source's health -- is identical either way, which is the point.
 */
class SourceRunner
{
    public function __construct(private readonly AdapterFactory $adapters) {}

    /**
     * @param  Closure(EventDraft, IngestRun): string  $handler
     *                                                           Returns one of: created, updated, skipped.
     */
    public function run(
        IngestSource $source,
        Closure $handler,
        bool $dryRun = false,
        ?int $limit = null,
    ): IngestRun {
        $startedAt = Carbon::now();
        $timer = microtime(true);

        $run = $source->runs()->create([
            'started_at' => $startedAt,
            'status' => 'running',
            'dry_run' => $dryRun,
        ]);

        $source->forceFill(['last_run_at' => $startedAt])->save();

        try {
            $adapter = $this->adapters->make($source);

            foreach ($adapter->fetch($source) as $item) {
                if ($limit !== null && $run->items_seen >= $limit) {
                    break;
                }

                $run->items_seen++;

                try {
                    $draft = $adapter->normalise($source, $item);
                } catch (Throwable $e) {
                    $run->items_failed++;
                    Log::warning('Ingest: normalise failed', [
                        'source' => $source->slug,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                // A null draft is an ordinary outcome, not a fault: test rows,
                // dateless listings and past events all end up here.
                if ($draft === null || ! $draft->isUpcoming()) {
                    $run->items_skipped++;

                    continue;
                }

                try {
                    match ($handler($draft, $run)) {
                        'created' => $run->items_created++,
                        'updated' => $run->items_updated++,
                        default => $run->items_skipped++,
                    };
                } catch (Throwable $e) {
                    $run->items_failed++;
                    Log::warning('Ingest: import failed', [
                        'source' => $source->slug,
                        'external_id' => $draft->externalId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $run->requests_made = $adapter->requestCount();

            $this->finish($run, $run->resolveStatus(), $timer);
            $this->recordSuccess($source, $run);
        } catch (Throwable $e) {
            $run->error = $e->getMessage();
            $this->finish($run, 'failed', $timer);
            $this->recordFailure($source, $e);

            Log::error('Ingest: run failed', [
                'source' => $source->slug,
                'error' => $e->getMessage(),
            ]);
        }

        return $run->refresh();
    }

    private function finish(IngestRun $run, string $status, float $timer): void
    {
        $run->status = $status;
        $run->finished_at = Carbon::now();
        $run->duration_ms = (int) ((microtime(true) - $timer) * 1000);
        $run->save();
    }

    private function recordSuccess(IngestSource $source, IngestRun $run): void
    {
        $source->forceFill([
            'last_success_at' => Carbon::now(),
            'last_status' => $run->status,
            'last_message' => sprintf(
                '%d seen, %d new, %d updated, %d skipped, %d failed',
                $run->items_seen,
                $run->items_created,
                $run->items_updated,
                $run->items_skipped,
                $run->items_failed,
            ),
            'consecutive_failures' => 0,
        ])->save();
    }

    private function recordFailure(IngestSource $source, Throwable $e): void
    {
        $failures = $source->consecutive_failures + 1;

        $source->forceFill([
            'last_status' => 'failed',
            'last_message' => $e->getMessage(),
            'consecutive_failures' => $failures,
        ])->save();

        // A source that has failed repeatedly is either broken or unwelcome.
        // Either way, stop knocking and let a human look at it.
        if ($failures >= 5) {
            $source->forceFill(['is_enabled' => false])->save();

            Log::error('Ingest: source disabled after repeated failures', [
                'source' => $source->slug,
                'failures' => $failures,
            ]);
        }
    }
}
