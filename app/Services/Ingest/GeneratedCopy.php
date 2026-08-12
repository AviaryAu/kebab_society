<?php

declare(strict_types=1);

namespace App\Services\Ingest;

/**
 * Original copy for one event, written from its facts.
 */
final readonly class GeneratedCopy
{
    public function __construct(
        public string $description,
        public string $metaDescription,
        public string $model,
    ) {}

    /**
     * Copy assembled from the facts without asking a model. Used when the
     * daily budget is gone or the API is unreachable, so an exhausted quota
     * degrades the writing rather than blocking the event.
     */
    public static function isTemplate(string $model): bool
    {
        return $model === 'template';
    }
}
