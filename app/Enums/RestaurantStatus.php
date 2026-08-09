<?php

declare(strict_types=1);

namespace App\Enums;

enum RestaurantStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case TemporarilyClosed = 'temporarily_closed';
    case PermanentlyClosed = 'permanently_closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Awaiting Society review',
            self::Published => 'Trading',
            self::TemporarilyClosed => 'Temporarily closed',
            self::PermanentlyClosed => 'Gone, but not forgotten',
        };
    }

    public function isDiscoverable(): bool
    {
        return $this === self::Published || $this === self::TemporarilyClosed;
    }
}
