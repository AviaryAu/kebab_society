<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much a source is trusted, which decides what we are allowed to take from
 * it. This is the enforcement point for the project's copyright posture, so
 * the values are deliberately about permission rather than quality.
 */
enum SourceTrust: string
{
    /** An API whose terms grant us the record and its artwork. */
    case Licensed = 'licensed';

    /** A venue or promoter publishing their own event on their own site. */
    case Verified = 'verified';

    /** An editorial lister. Facts and a citation only; never prose, never art. */
    case Signal = 'signal';

    public function label(): string
    {
        return match ($this) {
            self::Licensed => 'Licensed API',
            self::Verified => 'Venue or promoter',
            self::Signal => 'Editorial listing',
        };
    }

    /**
     * Whether we may download and re-host artwork from this source.
     */
    public function allowsImageImport(): bool
    {
        return in_array($this->value, config('ingest.images.allowed_trust', []), true);
    }

    /**
     * Whether records from this source may skip the review queue. Editorial
     * listings always get a human look before they go live.
     */
    public function allowsAutoPublish(): bool
    {
        return $this !== self::Signal;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
