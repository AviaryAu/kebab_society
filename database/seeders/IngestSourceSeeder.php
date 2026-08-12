<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SourceTier;
use App\Enums\SourceTrust;
use App\Models\IngestSource;
use Illuminate\Database\Seeder;

/**
 * The starting registry of Sydney event sources.
 *
 * Every entry was checked by hand before being listed here: robots.txt read,
 * structured data confirmed on a real page, and the publisher's own terms
 * considered. Sources that did not pass are still recorded, disabled, with the
 * reason in `notes` — a source we decided against is worth remembering, or
 * somebody will propose it again in six months.
 *
 * Everything starts with `auto_publish` and `allow_image_import` off. The first
 * run should fill the review queue so a person can see what a source actually
 * produces before it is trusted to publish on its own.
 */
class IngestSourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sources() as $source) {
            IngestSource::query()->updateOrCreate(
                ['slug' => $source['slug']],
                $source,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            /*
             * Verified 2026-08: 700 event URLs in the sitemap, every one
             * carrying clean schema.org Event data with venue and offers.
             * Council-published, permissive robots.txt. The best free/community
             * coverage in the city and the natural first source to trust.
             */
            [
                'name' => "City of Sydney What's On",
                'slug' => 'city-of-sydney',
                'adapter' => 'json-ld',
                'tier' => SourceTier::Structured,
                'trust' => SourceTrust::Verified,
                'website' => 'https://whatson.cityofsydney.nsw.gov.au',
                'sitemap_url' => 'https://whatson.cityofsydney.nsw.gov.au/api/sitemap.xml',
                'path_allowlist' => ['/events/'],
                'options' => ['max_pages' => 250],
                'default_category_slug' => 'arts',
                'frequency_minutes' => 720,
                'is_enabled' => true,
                'licence' => 'City of Sydney open data',
                'terms_url' => 'https://www.cityofsydney.nsw.gov.au/copyright',
                'notes' => 'Council listings: markets, community, free events, exhibitions. '
                    .'Tested 2026-08: ~11 usable events per 12 pages crawled.',
            ],

            /*
             * Verified 2026-08: ~1900 event pages with Event JSON-LD. Most are
             * archive years, which the pipeline drops as past — expect little
             * outside festival season and a flood in May.
             */
            [
                'name' => 'Vivid Sydney',
                'slug' => 'vivid-sydney',
                'adapter' => 'json-ld',
                'tier' => SourceTier::Structured,
                'trust' => SourceTrust::Verified,
                'website' => 'https://www.vividsydney.com',
                'sitemap_url' => 'https://www.vividsydney.com/sitemap.xml',
                'path_allowlist' => ['/event'],
                'options' => ['max_pages' => 200],
                'default_category_slug' => 'festivals',
                'frequency_minutes' => 1440,
                'is_enabled' => true,
                'licence' => 'Destination NSW',
                'notes' => 'Seasonal. Tested 2026-08: sitemap is almost entirely past '
                    .'festivals, so expect nothing outside May–June and a flood during it.',
            ],

            /*
             * Disabled after testing 2026-08.
             *
             * robots.txt is fine and the sitemap reads correctly, but the
             * structured data does not carry dates: their Event nodes contain
             * only @type, name, url, eventStatus and location, with
             * performance times loaded client-side afterwards. The handful of
             * entries that do have a startDate are stale archive pages.
             *
             * Worth revisiting with a dedicated adapter against whatever
             * endpoint their front end calls for performances. JSON-LD alone
             * cannot reach it.
             */
            [
                'name' => 'Sydney Opera House',
                'slug' => 'sydney-opera-house',
                'adapter' => 'json-ld',
                'tier' => SourceTier::Structured,
                'trust' => SourceTrust::Verified,
                'website' => 'https://www.sydneyoperahouse.com',
                'sitemap_url' => 'https://www.sydneyoperahouse.com/sitemap.xml',
                'path_allowlist' => [
                    '/whats-on', '/contemporary-music', '/classical-music', '/opera',
                    '/theatre', '/dance', '/comedy', '/cabaret', '/circus', '/cinema',
                    '/talks-and-ideas', '/kids-families', '/first-nations',
                    '/festivals-and-series', '/vivid-live',
                ],
                'options' => ['max_pages' => 200],
                'default_category_slug' => 'theatre',
                'frequency_minutes' => 720,
                'is_enabled' => false,
                'licence' => 'Sydney Opera House Trust',
                'notes' => 'DISABLED: Event JSON-LD carries no dates (loaded client-side); '
                    .'dated pages are stale archives. Needs a bespoke adapter. '
                    .'Path allowlist mirrors their robots.txt allow list exactly.',
            ],

            [
                'name' => 'Museum of Contemporary Art',
                'slug' => 'mca-australia',
                'adapter' => 'json-ld',
                'tier' => SourceTier::Structured,
                'trust' => SourceTrust::Verified,
                'website' => 'https://www.mca.com.au',
                'sitemap_url' => 'https://www.mca.com.au/sitemap.xml',
                'path_allowlist' => ['/exhibitions/', '/events-programs/'],
                'options' => ['max_pages' => 150],
                'default_category_slug' => 'arts',
                'frequency_minutes' => 1440,
                'is_enabled' => true,
                'licence' => 'MCA Australia',
                'notes' => 'Exhibitions and public programs. Tested 2026-08: works, but the '
                    .'sitemap paths hold many non-event pages, so a crawl is request-heavy.',
            ],

            /*
             * Disabled: needs a key. Free tier is 5000 calls a day at five a
             * second; get one at developer.ticketmaster.com, paste it into the
             * API key field and enable. Covers the arenas, the big rooms and
             * most sport — the single highest-value source once keyed.
             */
            [
                'name' => 'Ticketmaster',
                'slug' => 'ticketmaster',
                'adapter' => 'ticketmaster',
                'tier' => SourceTier::Api,
                'trust' => SourceTrust::Licensed,
                'website' => 'https://www.ticketmaster.com.au',
                'endpoint' => 'https://app.ticketmaster.com/discovery/v2/events.json',
                'options' => [
                    'country_code' => 'AU',
                    'market_id' => '302',
                    'window_days' => 30,
                    'horizon_days' => 180,
                    'size' => 200,
                ],
                'default_category_slug' => 'music',
                'frequency_minutes' => 360,
                'is_enabled' => false,
                'licence' => 'Ticketmaster Discovery API',
                'terms_url' => 'https://developer.ticketmaster.com/support/terms-of-use/',
                'notes' => 'DISABLED: add an API key, then enable. Free tier 5000/day.',
            ],

            /*
             * Disabled, deliberately, on two counts.
             *
             * Their robots.txt allows general crawlers but names and blocks
             * every AI crawler it can think of — GPTBot, Claudebot,
             * Google-Extended, PerplexityBot and the rest. We are an events
             * indexer rather than a training crawler, and we would take only
             * facts, but the intent of that file is not ambiguous and it is
             * their publication to set terms on.
             *
             * Separately: their Sydney events sitemap is currently empty, so
             * there is nothing there to take in any case.
             */
            [
                'name' => 'Broadsheet Sydney',
                'slug' => 'broadsheet',
                'adapter' => 'json-ld',
                'tier' => SourceTier::Editorial,
                'trust' => SourceTrust::Signal,
                'website' => 'https://www.broadsheet.com.au',
                'sitemap_url' => 'https://www.broadsheet.com.au/sitemap/sydney/events',
                'path_allowlist' => ['/sydney/'],
                'default_category_slug' => 'food-drink',
                'frequency_minutes' => 1440,
                'is_enabled' => false,
                'terms_url' => 'https://www.broadsheet.com.au/robots.txt',
                'notes' => 'DISABLED: robots.txt blocks AI crawlers by name, and their '
                    .'Sydney events sitemap is currently empty. Ask permission before enabling.',
            ],
        ];
    }
}
