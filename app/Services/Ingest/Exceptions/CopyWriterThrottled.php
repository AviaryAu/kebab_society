<?php

declare(strict_types=1);

namespace App\Services\Ingest\Exceptions;

use RuntimeException;

/**
 * The model provider asked us to slow down.
 *
 * Carries the wait so the queue can release the job for exactly as long as we
 * were told, rather than guessing. On a free tier this is ordinary traffic
 * shaping, not a failure.
 */
class CopyWriterThrottled extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Copy generation throttled; retry in {$retryAfter}s.");
    }
}
