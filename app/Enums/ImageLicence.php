<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The permission we hold over an event's hero image. Recorded per event so a
 * later audit can answer "why is this picture on our site?" without guessing.
 */
enum ImageLicence: string
{
    /** Supplied by an API whose terms cover display. */
    case Licensed = 'licensed';

    /** The venue's or promoter's own artwork for their own event. */
    case Owned = 'owned';

    /** Uploaded by an administrator. */
    case Editorial = 'editorial';

    /** No image we are entitled to use; the page falls back to a KS card. */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Licensed => 'Licensed from source',
            self::Owned => 'Venue supplied',
            self::Editorial => 'Keep Sydney Live',
            self::None => 'No image',
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
