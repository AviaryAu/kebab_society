<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DataSource;
use App\Enums\RestaurantStatus;
use App\Enums\VerificationStatus;
use App\Models\KebabStyle;
use App\Models\Restaurant;
use App\Models\Suburb;
use App\Services\KebabScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sample Sydney kebab shops.
 *
 * IMPORTANT: every business below is fictional. The suburbs and coordinates are
 * real so the map behaves realistically, but the names, ratings and verdicts are
 * invented placeholder data for development. Nothing here is presented as a real
 * business or a real review.
 */
class RestaurantSeeder extends Seeder
{
    public function __construct(private readonly KebabScoringService $scoring) {}

    public function run(): void
    {
        $suburbs = Suburb::query()->pluck('id', 'slug');
        $styles = KebabStyle::query()->pluck('id', 'slug');

        foreach ($this->restaurants() as $data) {
            $suburbId = $suburbs[$data['suburb']] ?? null;

            if ($suburbId === null) {
                continue;
            }

            $restaurant = Restaurant::query()->updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'address_line' => $data['address'],
                    'suburb_id' => $suburbId,
                    'postcode' => $data['postcode'],
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'phone' => $data['phone'],
                    'website' => $data['website'] ?? null,
                    'google_rating' => $data['google'][0],
                    'google_review_count' => $data['google'][1],
                    'google_data_updated_at' => Carbon::now()->subDays(3),
                    'opening_hours' => $this->hours($data['hours']),
                    'price_level' => $data['price'],
                    'status' => $data['status'] ?? RestaurantStatus::Published,
                    'society_rating' => $data['society'][0],
                    'society_review_count' => $data['society'][1],
                    'check_in_count' => $data['check_ins'],
                    'editorial_adjustment' => $data['adjustment'] ?? 0,
                    'editorial_note' => $data['note'] ?? null,
                    'verification_status' => ($data['approved'] ?? false)
                        ? VerificationStatus::SocietyCertified
                        : VerificationStatus::Unverified,
                    'society_approved_at' => ($data['approved'] ?? false) ? Carbon::now()->subMonths(2) : null,
                    'data_source' => DataSource::SeedData,
                ],
            );

            $restaurant->kebabStyles()->sync(
                collect($data['styles'])
                    ->filter(fn (string $slug): bool => isset($styles[$slug]))
                    ->mapWithKeys(fn (string $slug, int $index): array => [
                        $styles[$slug] => ['is_signature' => $index === 0],
                    ])
                    ->all()
            );

            $this->scoring->apply($restaurant);
        }
    }

    /**
     * Expand a named trading pattern into a weekly schedule.
     *
     * @return array<string, array<int, array{open: string, close: string}>>
     */
    private function hours(string $pattern): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        $sessions = match ($pattern) {
            'all_hours' => ['00:00' => '24:00'],
            'very_late' => ['11:00' => '05:00'],
            'late' => ['10:30' => '03:00'],
            'evening' => ['16:00' => '02:00'],
            'standard' => ['10:00' => '22:00'],
            'daytime' => ['08:00' => '17:00'],
            default => ['11:00' => '23:00'],
        };

        $schedule = [];

        foreach ($days as $day) {
            foreach ($sessions as $open => $close) {
                // Weekends run an hour later than the weekday roster.
                $isWeekend = in_array($day, ['fri', 'sat'], true);
                $schedule[$day][] = [
                    'open' => $open,
                    'close' => $isWeekend && $close !== '24:00'
                        ? $this->addHour($close)
                        : $close,
                ];
            }
        }

        return $schedule;
    }

    private function addHour(string $time): string
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return sprintf('%02d:%02d', ($hours + 1) % 24, $minutes);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function restaurants(): array
    {
        return [
            [
                'name' => 'Pitt Street Doner Bar',
                'suburb' => 'sydney-cbd', 'postcode' => '2000',
                'address' => '412 Pitt Street', 'lat' => -33.8760, 'lng' => 151.2069,
                'phone' => '(02) 9200 1101', 'website' => 'https://example.com/pitt-street-doner-bar',
                'google' => [4.4, 1820], 'society' => [4.6, 68], 'check_ins' => 412,
                'styles' => ['doner', 'kebab', 'mixed', 'turkish'], 'hours' => 'late', 'price' => 2,
                'approved' => true, 'adjustment' => 3,
                'note' => 'Bread toasted to the point of structural confidence. The Society approves.',
                'description' => 'A narrow shopfront that has quietly fed the CBD for years. Meat is shaved to order, never pre-cut, and the garlic sauce is applied with the seriousness of a licensed trade.',
            ],
            [
                'name' => 'The Midnight Spit',
                'suburb' => 'sydney-cbd', 'postcode' => '2000',
                'address' => '88 Liverpool Street', 'lat' => -33.8770, 'lng' => 151.2075,
                'phone' => '(02) 9200 1102', 'website' => null,
                'google' => [4.1, 940], 'society' => [4.2, 41], 'check_ins' => 287,
                'styles' => ['kebab', 'hsp', 'lamb', 'turkish'], 'hours' => 'very_late', 'price' => 2,
                'approved' => false, 'adjustment' => 2,
                'note' => 'Open until 5am. God bless them.',
                'description' => 'Trades almost entirely after dark. The queue at 2am is a reliable indicator of a good night unfolding elsewhere in the city.',
            ],
            [
                'name' => 'Crown Street Charcoal Kebabs',
                'suburb' => 'surry-hills', 'postcode' => '2010',
                'address' => '301 Crown Street', 'lat' => -33.8848, 'lng' => 151.2135,
                'phone' => '(02) 9200 1103', 'website' => 'https://example.com/crown-street-charcoal',
                'google' => [4.6, 2130], 'society' => [4.8, 112], 'check_ins' => 903,
                'styles' => ['kebab', 'iskender', 'mixed', 'turkish'], 'hours' => 'late', 'price' => 3,
                'approved' => true, 'adjustment' => 3,
                'note' => 'Charcoal, not gas. The difference is not subtle and the Society will not pretend otherwise.',
                'description' => 'Genuine charcoal grilling in a suburb that mostly gave up on it. Expect smoke, a short menu and no interest in your dietary improvisations.',
            ],
            [
                'name' => 'Redfern Rotisserie',
                'suburb' => 'redfern', 'postcode' => '2016',
                'address' => '17 Redfern Street', 'lat' => -33.8930, 'lng' => 151.2010,
                'phone' => '(02) 9200 1104', 'website' => null,
                'google' => [4.0, 610], 'society' => [3.9, 22], 'check_ins' => 141,
                'styles' => ['chicken', 'kebab', 'plate'], 'hours' => 'standard', 'price' => 2,
                'description' => 'A charcoal chicken shop that discovered kebabs and never looked back. The chips are the real headline.',
            ],
            [
                'name' => 'King Street Doner Co.',
                'suburb' => 'newtown', 'postcode' => '2042',
                'address' => '244 King Street', 'lat' => -33.8961, 'lng' => 151.1802,
                'phone' => '(02) 9200 1105', 'website' => 'https://example.com/king-street-doner',
                'google' => [4.5, 3120], 'society' => [4.5, 96], 'check_ins' => 1204,
                'styles' => ['doner', 'kebab', 'mixed', 'falafel', 'turkish'], 'hours' => 'very_late', 'price' => 2,
                'approved' => true, 'adjustment' => 2,
                'note' => 'The benchmark against which the Inner West measures itself.',
                'description' => 'The unofficial finish line of King Street. Consistent, fast, and entirely unbothered by whatever is happening outside at 3am.',
            ],
            [
                'name' => 'Enmore Road Kebab Lounge',
                'suburb' => 'newtown', 'postcode' => '2042',
                'address' => '96 Enmore Road', 'lat' => -33.8996, 'lng' => 151.1762,
                'phone' => '(02) 9200 1106', 'website' => null,
                'google' => [3.8, 480], 'society' => [3.6, 18], 'check_ins' => 88,
                'styles' => ['kebab', 'hsp', 'chicken'], 'hours' => 'evening', 'price' => 1,
                'adjustment' => -2,
                'note' => 'Generous portions, ambitious structural decisions.',
                'description' => 'Enormous serves at a price that raises questions. Bring napkins. Bring several.',
            ],
            [
                'name' => 'Anatolia Charcoal Kebabs',
                'suburb' => 'marrickville', 'postcode' => '2204',
                'address' => '215 Marrickville Road', 'lat' => -33.9105, 'lng' => 151.1571,
                'phone' => '(02) 9200 1107', 'website' => 'https://example.com/anatolia-charcoal',
                'google' => [4.7, 1640], 'society' => [4.9, 134], 'check_ins' => 1101,
                'styles' => ['kebab', 'iskender', 'lamb', 'gozleme', 'turkish'], 'hours' => 'late', 'price' => 2,
                'approved' => true, 'adjustment' => 3,
                'note' => 'Bread baked on site. Meat rested properly. The Society has spoken.',
                'description' => 'A family operation that treats bread as a discipline rather than a container. Widely regarded within the Society as the standard-setter for the Inner West.',
            ],
            [
                'name' => 'Illawarra Road HSP House',
                'suburb' => 'marrickville', 'postcode' => '2204',
                'address' => '58 Illawarra Road', 'lat' => -33.9126, 'lng' => 151.1544,
                'phone' => '(02) 9200 1108', 'website' => null,
                'google' => [4.3, 720], 'society' => [4.4, 57], 'check_ins' => 466,
                'styles' => ['hsp', 'kebab', 'mixed'], 'hours' => 'evening', 'price' => 2,
                'description' => 'Specialists. The snack pack arrives in a container that has clearly been engineered for the job.',
            ],
            [
                'name' => 'Norton Street Shawarma',
                'suburb' => 'leichhardt', 'postcode' => '2040',
                'address' => '133 Norton Street', 'lat' => -33.8824, 'lng' => 151.1567,
                'phone' => '(02) 9200 1109', 'website' => null,
                'google' => [4.2, 530], 'society' => [4.1, 29], 'check_ins' => 173,
                'styles' => ['shawarma', 'lebanese', 'chicken', 'falafel'], 'hours' => 'standard', 'price' => 2,
                'description' => 'Toum so assertive it should require a permit. Garlic intensity: extreme.',
            ],
            [
                'name' => 'Campbell Parade Kebab Shack',
                'suburb' => 'bondi-beach', 'postcode' => '2026',
                'address' => '180 Campbell Parade', 'lat' => -33.8898, 'lng' => 151.2765,
                'phone' => '(02) 9200 1110', 'website' => 'https://example.com/campbell-parade-kebabs',
                'google' => [3.9, 2210], 'society' => [3.4, 46], 'check_ins' => 621,
                'styles' => ['kebab', 'chicken', 'mixed'], 'hours' => 'very_late', 'price' => 3,
                'adjustment' => -4,
                'note' => 'Priced for the postcode rather than the product.',
                'description' => 'Perfectly serviceable, generously located, confidently priced. The Society acknowledges the rent.',
            ],
            [
                'name' => 'Sunrise Doner',
                'suburb' => 'bondi-beach', 'postcode' => '2026',
                'address' => '4 Hall Street', 'lat' => -33.8917, 'lng' => 151.2740,
                'phone' => '(02) 9200 1111', 'website' => null,
                'google' => [4.4, 810], 'society' => [4.3, 33], 'check_ins' => 258,
                'styles' => ['doner', 'kebab', 'lamb', 'turkish'], 'hours' => 'late', 'price' => 2,
                'description' => 'Quietly excellent, one street back from the noise. Locals would prefer you did not read this.',
            ],
            [
                'name' => 'Belmore Road Charcoal',
                'suburb' => 'randwick', 'postcode' => '2031',
                'address' => '92 Belmore Road', 'lat' => -33.9151, 'lng' => 151.2419,
                'phone' => '(02) 9200 1112', 'website' => null,
                'google' => [4.3, 990], 'society' => [4.2, 38], 'check_ins' => 312,
                'styles' => ['kebab', 'chicken', 'plate', 'greek'], 'hours' => 'standard', 'price' => 2,
                'description' => 'Feeds a hospital, a university and a racecourse. Has never once been observed to be quiet.',
            ],
            [
                'name' => 'Coogee Bay Kebab Rooms',
                'suburb' => 'coogee', 'postcode' => '2034',
                'address' => '206 Coogee Bay Road', 'lat' => -33.9198, 'lng' => 151.2549,
                'phone' => '(02) 9200 1113', 'website' => null,
                'google' => [3.7, 640], 'society' => [3.3, 21], 'check_ins' => 199,
                'styles' => ['kebab', 'hsp', 'mixed'], 'hours' => 'very_late', 'price' => 2,
                'adjustment' => -3,
                'note' => 'Improves dramatically after midnight, which may say more about the customer than the kebab.',
                'description' => 'A late-night institution with a variable relationship to consistency. Go with the mixed and low expectations.',
            ],
            [
                'name' => 'Flight Path Kebabs',
                'suburb' => 'mascot', 'postcode' => '2020',
                'address' => '1042 Botany Road', 'lat' => -33.9268, 'lng' => 151.1911,
                'phone' => '(02) 9200 1114', 'website' => null,
                'google' => [4.1, 380], 'society' => [4.0, 16], 'check_ins' => 97,
                'styles' => ['kebab', 'doner', 'chicken'], 'hours' => 'all_hours', 'price' => 2,
                'description' => 'Open permanently, because the airport is. The 4am shift is a genuine public service.',
            ],
            [
                'name' => 'Cooks River Doner',
                'suburb' => 'wolli-creek', 'postcode' => '2205',
                'address' => '15 Discovery Point Place', 'lat' => -33.9327, 'lng' => 151.1533,
                'phone' => '(02) 9200 1115', 'website' => null,
                'google' => [4.0, 290], 'society' => [3.8, 12], 'check_ins' => 64,
                'styles' => ['doner', 'kebab', 'mixed'], 'hours' => 'standard', 'price' => 2,
                'description' => 'New, tidy, and still working out how much garlic sauce Sydney actually requires. The answer is more.',
            ],
            [
                'name' => 'Haldon Street Charcoal',
                'suburb' => 'lakemba', 'postcode' => '2195',
                'address' => '76 Haldon Street', 'lat' => -33.9188, 'lng' => 151.0762,
                'phone' => '(02) 9200 1116', 'website' => null,
                'google' => [4.8, 2740], 'society' => [4.9, 187], 'check_ins' => 1487,
                'styles' => ['kebab', 'shawarma', 'lamb', 'lebanese'], 'hours' => 'very_late', 'price' => 1,
                'approved' => true, 'adjustment' => 4,
                'note' => 'The Society regards Haldon Street as sacred ground and this shop as its cathedral.',
                'description' => 'Charcoal, patience and a spit that never stops turning. Cheap, enormous and almost impossible to fault.',
            ],
            [
                'name' => 'Sabah Sweets & Shawarma',
                'suburb' => 'lakemba', 'postcode' => '2195',
                'address' => '141 Haldon Street', 'lat' => -33.9202, 'lng' => 151.0748,
                'phone' => '(02) 9200 1117', 'website' => null,
                'google' => [4.5, 1120], 'society' => [4.4, 63], 'check_ins' => 522,
                'styles' => ['shawarma', 'lebanese', 'chicken', 'falafel'], 'hours' => 'very_late', 'price' => 1,
                'description' => 'Kebabs at the front, an unreasonable quantity of sweets at the back. Plan your evening accordingly.',
            ],
            [
                'name' => 'Chapel Road Kebab Palace',
                'suburb' => 'bankstown', 'postcode' => '2200',
                'address' => '38 Chapel Road', 'lat' => -33.9171, 'lng' => 151.0342,
                'phone' => '(02) 9200 1118', 'website' => 'https://example.com/chapel-road-kebab-palace',
                'google' => [4.6, 1980], 'society' => [4.6, 88], 'check_ins' => 764,
                'styles' => ['kebab', 'hsp', 'mixed', 'lebanese'], 'hours' => 'very_late', 'price' => 1,
                'approved' => true, 'adjustment' => 2,
                'note' => 'Value that borders on a clerical error.',
                'description' => 'Portion sizes that suggest a misunderstanding of the word "snack". The Society considers this a feature.',
            ],
            [
                'name' => 'Punchbowl Charcoal Chicken',
                'suburb' => 'punchbowl', 'postcode' => '2196',
                'address' => '812 Punchbowl Road', 'lat' => -33.9243, 'lng' => 151.0561,
                'phone' => '(02) 9200 1119', 'website' => null,
                'google' => [4.4, 870], 'society' => [4.2, 34], 'check_ins' => 289,
                'styles' => ['chicken', 'kebab', 'plate', 'lebanese'], 'hours' => 'standard', 'price' => 1,
                'description' => 'The chicken is the point. The kebab is the reward for noticing.',
            ],
            [
                'name' => 'Auburn Kebab Ocakbasi',
                'suburb' => 'auburn', 'postcode' => '2144',
                'address' => '44 Auburn Road', 'lat' => -33.8489, 'lng' => 151.0338,
                'phone' => '(02) 9200 1120', 'website' => null,
                'google' => [4.7, 1560], 'society' => [4.8, 101], 'check_ins' => 812,
                'styles' => ['iskender', 'kebab', 'lamb', 'turkish', 'gozleme'], 'hours' => 'late', 'price' => 2,
                'approved' => true, 'adjustment' => 3,
                'note' => 'Grilled over coals in full view. Nothing to hide, and nothing that should be hidden.',
                'description' => 'A proper ocakbasi grill. Sit at the counter, watch the coals, and accept that you will be there longer than planned.',
            ],
            [
                'name' => 'South Street Iskender House',
                'suburb' => 'granville', 'postcode' => '2142',
                'address' => '21 South Street', 'lat' => -33.8338, 'lng' => 151.0104,
                'phone' => '(02) 9200 1121', 'website' => null,
                'google' => [4.5, 930], 'society' => [4.5, 52], 'check_ins' => 377,
                'styles' => ['iskender', 'plate', 'lamb', 'turkish'], 'hours' => 'standard', 'price' => 2,
                'description' => 'Iskender served on bread that soaks up everything it is given. Cutlery is not optional here.',
            ],
            [
                'name' => 'Church Street Doner Works',
                'suburb' => 'parramatta', 'postcode' => '2150',
                'address' => '188 Church Street', 'lat' => -33.8155, 'lng' => 151.0043,
                'phone' => '(02) 9200 1122', 'website' => 'https://example.com/church-street-doner-works',
                'google' => [4.3, 1440], 'society' => [4.2, 61], 'check_ins' => 501,
                'styles' => ['doner', 'kebab', 'mixed', 'turkish'], 'hours' => 'late', 'price' => 2,
                'description' => 'Efficient, well-run and busy from noon. The mixed with everything is the correct order.',
            ],
            [
                'name' => 'Parramatta Late Night Kebabs',
                'suburb' => 'parramatta', 'postcode' => '2150',
                'address' => '9 Horwood Place', 'lat' => -33.8168, 'lng' => 151.0012,
                'phone' => '(02) 9200 1123', 'website' => null,
                'google' => [3.6, 520], 'society' => [3.2, 24], 'check_ins' => 233,
                'styles' => ['kebab', 'hsp', 'chicken'], 'hours' => 'very_late', 'price' => 1,
                'adjustment' => -5,
                'note' => 'The Society has concerns about the sauce-to-structure ratio.',
                'description' => 'Late, cheap and dependable in the narrowest sense of the word. Order it wrapped and walk quickly.',
            ],
            [
                'name' => 'Main Street Charcoal Kebabs',
                'suburb' => 'blacktown', 'postcode' => '2148',
                'address' => '62 Main Street', 'lat' => -33.7683, 'lng' => 150.9071,
                'phone' => '(02) 9200 1124', 'website' => null,
                'google' => [4.4, 1080], 'society' => [4.3, 44], 'check_ins' => 351,
                'styles' => ['kebab', 'lamb', 'mixed', 'turkish'], 'hours' => 'late', 'price' => 1,
                'description' => 'Unfussy, well-seasoned and reliably busy. Blacktown has quietly had this one sorted for years.',
            ],
            [
                'name' => 'Macquarie Mall Shawarma',
                'suburb' => 'liverpool', 'postcode' => '2170',
                'address' => '14 Macquarie Street', 'lat' => -33.9209, 'lng' => 150.9245,
                'phone' => '(02) 9200 1125', 'website' => null,
                'google' => [4.2, 760], 'society' => [4.0, 27], 'check_ins' => 204,
                'styles' => ['shawarma', 'lebanese', 'chicken', 'falafel'], 'hours' => 'standard', 'price' => 1,
                'description' => 'Pickled turnip, proper garlic and a queue that moves with alarming speed.',
            ],
            [
                'name' => 'Beamish Street Kebab Co.',
                'suburb' => 'campsie', 'postcode' => '2194',
                'address' => '210 Beamish Street', 'lat' => -33.9119, 'lng' => 151.1021,
                'phone' => '(02) 9200 1126', 'website' => null,
                'google' => [4.1, 430], 'society' => [3.9, 19], 'check_ins' => 118,
                'styles' => ['kebab', 'doner', 'chicken'], 'hours' => 'evening', 'price' => 1,
                'description' => 'Small, fast and largely undiscovered. The Society is monitoring its trajectory with interest.',
            ],
            [
                'name' => 'Canterbury Charcoal Grill',
                'suburb' => 'canterbury', 'postcode' => '2193',
                'address' => '3 Canterbury Road', 'lat' => -33.9118, 'lng' => 151.1191,
                'phone' => '(02) 9200 1127', 'website' => null,
                'google' => [3.9, 350], 'society' => [3.7, 14], 'check_ins' => 76,
                'styles' => ['kebab', 'chicken', 'plate'], 'hours' => 'standard', 'price' => 1,
                'description' => 'A neighbourhood grill doing exactly what it says. No surprises, in either direction.',
            ],
            [
                'name' => 'Forest Road Doner',
                'suburb' => 'hurstville', 'postcode' => '2220',
                'address' => '270 Forest Road', 'lat' => -33.9673, 'lng' => 151.1019,
                'phone' => '(02) 9200 1128', 'website' => null,
                'google' => [4.0, 500], 'society' => [3.8, 20], 'check_ins' => 129,
                'styles' => ['doner', 'kebab', 'mixed'], 'hours' => 'late', 'price' => 2,
                'description' => 'Holding the line for kebabs in a suburb with very different culinary priorities.',
            ],
            [
                'name' => 'Railway Parade Kebabs',
                'suburb' => 'kogarah', 'postcode' => '2217',
                'address' => '48 Railway Parade', 'lat' => -33.9634, 'lng' => 151.1341,
                'phone' => '(02) 9200 1129', 'website' => null,
                'google' => [3.5, 260], 'society' => [3.0, 11], 'check_ins' => 58,
                'styles' => ['kebab', 'chicken'], 'hours' => 'standard', 'price' => 1,
                'adjustment' => -3,
                'note' => 'Bread fatigue is a recurring theme in Society reports.',
                'description' => 'Convenient to the station and honest about what it is. The Society suggests eating it immediately.',
            ],
            [
                'name' => 'Victoria Avenue Kebab Bar',
                'suburb' => 'chatswood', 'postcode' => '2067',
                'address' => '390 Victoria Avenue', 'lat' => -33.7962, 'lng' => 151.1812,
                'phone' => '(02) 9200 1130', 'website' => null,
                'google' => [4.2, 690], 'society' => [4.1, 31], 'check_ins' => 214,
                'styles' => ['kebab', 'doner', 'mixed', 'turkish'], 'hours' => 'standard', 'price' => 3,
                'description' => 'Neat, quick and slightly corporate. Performs admirably under lunchtime pressure.',
            ],
            [
                'name' => 'Miller Street Doner',
                'suburb' => 'north-sydney', 'postcode' => '2060',
                'address' => '112 Miller Street', 'lat' => -33.8382, 'lng' => 151.2065,
                'phone' => '(02) 9200 1131', 'website' => null,
                'google' => [4.0, 820], 'society' => [3.8, 26], 'check_ins' => 167,
                'styles' => ['doner', 'kebab', 'chicken', 'plate'], 'hours' => 'daytime', 'price' => 3,
                'adjustment' => -2,
                'note' => 'Closes before anyone actually needs a kebab.',
                'description' => 'Built entirely around the office lunch rush. Excellent at 12:30pm, theoretical at 9pm.',
            ],
            [
                'name' => 'The Corso Kebab Co.',
                'suburb' => 'manly', 'postcode' => '2095',
                'address' => '31 The Corso', 'lat' => -33.7975, 'lng' => 151.2857,
                'phone' => '(02) 9200 1132', 'website' => null,
                'google' => [4.1, 1310], 'society' => [3.9, 37], 'check_ins' => 341,
                'styles' => ['kebab', 'hsp', 'mixed'], 'hours' => 'late', 'price' => 3,
                'description' => 'Feeds the ferry crowd with impressive stamina. Sand in the shop is considered part of the experience.',
            ],
            [
                'name' => 'Dee Why Beach Charcoal',
                'suburb' => 'dee-why', 'postcode' => '2099',
                'address' => '8 The Strand', 'lat' => -33.7524, 'lng' => 151.2937,
                'phone' => '(02) 9200 1133', 'website' => null,
                'google' => [4.3, 540], 'society' => [4.2, 23], 'check_ins' => 148,
                'styles' => ['kebab', 'lamb', 'turkish'], 'hours' => 'late', 'price' => 2,
                'description' => 'A genuinely good charcoal kebab in a part of Sydney that rarely gets credit for one.',
            ],
            [
                'name' => 'Hills Charcoal Kebabs',
                'suburb' => 'castle-hill', 'postcode' => '2154',
                'address' => '260 Old Northern Road', 'lat' => -33.7311, 'lng' => 151.0062,
                'phone' => '(02) 9200 1134', 'website' => null,
                'google' => [4.4, 610], 'society' => [4.3, 25], 'check_ins' => 162,
                'styles' => ['kebab', 'mixed', 'plate', 'turkish'], 'hours' => 'standard', 'price' => 2,
                'description' => 'Suburban, spacious and surprisingly serious about its marinade.',
            ],
            [
                'name' => 'Cronulla Beachfront Kebabs',
                'suburb' => 'cronulla', 'postcode' => '2230',
                'address' => '22 Kingsway', 'lat' => -34.0552, 'lng' => 151.1534,
                'phone' => '(02) 9200 1135', 'website' => null,
                'google' => [3.8, 470], 'society' => [3.5, 17], 'check_ins' => 121,
                'styles' => ['kebab', 'chicken', 'hsp'], 'hours' => 'late', 'price' => 2,
                'description' => 'Summer trade dictates everything. In January it is a machine; in June it is a rumour.',
            ],
            [
                'name' => 'Kingsway Kebab House',
                'suburb' => 'miranda', 'postcode' => '2228',
                'address' => '540 Kingsway', 'lat' => -34.0359, 'lng' => 151.1013,
                'phone' => '(02) 9200 1136', 'website' => null,
                'google' => [4.2, 700], 'society' => [4.0, 28], 'check_ins' => 187,
                'styles' => ['kebab', 'doner', 'mixed'], 'hours' => 'standard', 'price' => 2,
                'description' => 'The Shire\'s most dependable wrap. Sauce distribution is genuinely well managed.',
            ],
            [
                'name' => 'Rosebery Road Kebabs',
                'suburb' => 'mascot', 'postcode' => '2020',
                'address' => '5 Coward Street', 'lat' => -33.9290, 'lng' => 151.1873,
                'phone' => '(02) 9200 1137', 'website' => null,
                'google' => [3.4, 180], 'society' => [2.9, 9], 'check_ins' => 41,
                'styles' => ['kebab', 'chicken'], 'hours' => 'daytime', 'price' => 1,
                'adjustment' => -4,
                'note' => 'Reports consistently describe the bread as "present".',
                'description' => 'The Society records its existence for completeness. Expectations should be calibrated accordingly.',
            ],
            [
                'name' => 'Old Canterbury Kebab Room',
                'suburb' => 'canterbury', 'postcode' => '2193',
                'address' => '188 Canterbury Road', 'lat' => -33.9096, 'lng' => 151.1152,
                'phone' => '(02) 9200 1138', 'website' => null,
                'google' => [4.0, 210], 'society' => [3.9, 8], 'check_ins' => 33,
                'styles' => ['kebab', 'mixed', 'turkish'], 'hours' => 'standard', 'price' => 1,
                'status' => RestaurantStatus::TemporarilyClosed,
                'description' => 'Closed while the grill is rebuilt. The Society is monitoring the situation closely and with some anxiety.',
            ],
        ];
    }
}
