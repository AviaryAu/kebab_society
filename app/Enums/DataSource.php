<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a piece of restaurant data came from. Society data must never be
 * presented as though Google supplied it, and vice versa.
 */
enum DataSource: string
{
    case GooglePlaces = 'google_places';
    case SocietyAdmin = 'society_admin';
    case SocietyMember = 'society_member';
    case RestaurantOwner = 'restaurant_owner';
    case ImportedDataset = 'imported_dataset';
    case SeedData = 'seed_data';

    public function label(): string
    {
        return match ($this) {
            self::GooglePlaces => 'Google Places',
            self::SocietyAdmin => 'Kebab Society administrator',
            self::SocietyMember => 'Kebab Society member',
            self::RestaurantOwner => 'Restaurant owner',
            self::ImportedDataset => 'Imported dataset',
            self::SeedData => 'Sample data',
        };
    }
}
