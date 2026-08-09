<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Suburb;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuburbSeeder extends Seeder
{
    /**
     * Sydney suburbs the Society currently patrols.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const SUBURBS = [
        ['Sydney CBD', '2000', 'Sydney CBD', -33.8688, 151.2093],
        ['Surry Hills', '2010', 'Inner City', -33.8860, 151.2110],
        ['Redfern', '2016', 'Inner City', -33.8926, 151.2044],
        ['Newtown', '2042', 'Inner West', -33.8983, 151.1793],
        ['Marrickville', '2204', 'Inner West', -33.9111, 151.1550],
        ['Leichhardt', '2040', 'Inner West', -33.8836, 151.1570],
        ['Bondi Beach', '2026', 'Eastern Suburbs', -33.8908, 151.2743],
        ['Randwick', '2031', 'Eastern Suburbs', -33.9145, 151.2410],
        ['Coogee', '2034', 'Eastern Suburbs', -33.9205, 151.2570],
        ['Mascot', '2020', 'South Sydney', -33.9276, 151.1899],
        ['Wolli Creek', '2205', 'South Sydney', -33.9310, 151.1520],
        ['Lakemba', '2195', 'South West', -33.9192, 151.0754],
        ['Bankstown', '2200', 'South West', -33.9176, 151.0350],
        ['Punchbowl', '2196', 'South West', -33.9250, 151.0550],
        ['Auburn', '2144', 'Western Sydney', -33.8494, 151.0330],
        ['Granville', '2142', 'Western Sydney', -33.8330, 151.0110],
        ['Parramatta', '2150', 'Parramatta', -33.8150, 151.0000],
        ['Blacktown', '2148', 'Blacktown', -33.7688, 150.9060],
        ['Liverpool', '2170', 'Liverpool', -33.9200, 150.9239],
        ['Campsie', '2194', 'Canterbury', -33.9114, 151.1030],
        ['Canterbury', '2193', 'Canterbury', -33.9110, 151.1180],
        ['Hurstville', '2220', 'St George', -33.9670, 151.1030],
        ['Kogarah', '2217', 'St George', -33.9640, 151.1330],
        ['Chatswood', '2067', 'North Shore', -33.7969, 151.1802],
        ['North Sydney', '2060', 'North Shore', -33.8390, 151.2070],
        ['Manly', '2095', 'Northern Beaches', -33.7969, 151.2870],
        ['Dee Why', '2099', 'Northern Beaches', -33.7540, 151.2900],
        ['Castle Hill', '2154', 'Hills District', -33.7320, 151.0050],
        ['Cronulla', '2230', 'Sutherland Shire', -34.0560, 151.1520],
        ['Miranda', '2228', 'Sutherland Shire', -34.0350, 151.1000],
    ];

    public function run(): void
    {
        foreach (self::SUBURBS as [$name, $postcode, $region, $latitude, $longitude]) {
            Suburb::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'postcode' => $postcode,
                    'region' => $region,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            );
        }
    }
}
