<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The mechanism a source is read by. Tiers exist so the pipeline can reason
 * about a source without knowing which adapter is behind it.
 */
enum SourceTier: string
{
    /** A documented API returning structured records. */
    case Api = 'api';

    /** Schema.org JSON-LD, iCalendar or a sitemap crawl. */
    case Structured = 'structured';

    /** An editorial listing read for facts alone. */
    case Editorial = 'editorial';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'Official API',
            self::Structured => 'Structured data',
            self::Editorial => 'Editorial listing',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
