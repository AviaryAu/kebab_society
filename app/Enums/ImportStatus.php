<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a staged import sits between arriving from a source and becoming a
 * published event.
 */
enum ImportStatus: string
{
    /** Waiting for a human in /admin/imports. */
    case Pending = 'pending';

    /** Published without review because the source was trusted enough. */
    case Auto = 'auto';

    /** A reviewer accepted it. */
    case Approved = 'approved';

    /** A reviewer rejected it; we remember the fingerprint so it stays gone. */
    case Rejected = 'rejected';

    /** Folded into an event we already had, usually from a better source. */
    case Merged = 'merged';

    /** The adapter could not make a usable event out of the payload. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Auto => 'Published automatically',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Merged => 'Merged',
            self::Failed => 'Failed',
        };
    }

    /**
     * Resolved imports are eligible for pruning; open ones are not.
     */
    public function isResolved(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
