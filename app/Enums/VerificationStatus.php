<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case SocietyCertified = 'society_certified';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Verified => 'Verified',
            self::SocietyCertified => 'Society Certified',
        };
    }
}
