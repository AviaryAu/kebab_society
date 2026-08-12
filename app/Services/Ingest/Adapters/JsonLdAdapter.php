<?php

declare(strict_types=1);

namespace App\Services\Ingest\Adapters;

use App\Models\IngestSource;
use App\Services\Ingest\Contracts\SourceAdapter;
use App\Services\Ingest\EventDraft;
use App\Services\Ingest\Http\PoliteClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Reads schema.org Event data that publishers already embed for search engines.
 *
 * This is the workhorse for the long tail of Sydney venues. Almost every site
 * that wants its gigs in Google publishes a JSON-LD block describing them, so
 * we read the same structured data Google does rather than guessing at their
 * markup. When a venue redesigns, this keeps working.
 *
 * Pages are discovered from the source's sitemap, filtered by its path
 * allowlist, and every fetch goes through PoliteClient — so robots.txt is
 * honoured even on sites that allow only certain sections.
 */
class JsonLdAdapter implements SourceAdapter
{
    public function __construct(private readonly PoliteClient $client) {}

    public function fetch(IngestSource $source): iterable
    {
        $urls = $this->discover($source);
        $seen = 0;
        $max = (int) Arr::get($source->options ?? [], 'max_pages', 300);

        foreach ($urls as $url) {
            if ($seen >= $max) {
                return;
            }

            $seen++;

            $response = $this->client->get($url);

            if ($response === null || ! $response->successful()) {
                continue;
            }

            foreach ($this->extractEvents($response->body()) as $event) {
                yield $event + ['@sourceUrl' => $url];
            }
        }
    }

    public function normalise(IngestSource $source, array $item): ?EventDraft
    {
        $start = $this->parseDate(Arr::get($item, 'startDate'));

        if ($start === null) {
            return null;
        }

        $status = (string) Arr::get($item, 'eventStatus', '');

        if (Str::contains($status, ['Cancelled', 'Postponed'])) {
            return null;
        }

        $title = $this->text(Arr::get($item, 'name'));

        if ($title === '') {
            return null;
        }

        $location = $this->firstOf(Arr::get($item, 'location'));
        $url = $this->text(Arr::get($item, 'url')) ?: (string) Arr::get($item, '@sourceUrl');

        return new EventDraft(
            // Publishers rarely give a stable id, so identity comes from the
            // page plus the date: the same URL listing a season of dates must
            // not collapse into one record.
            externalId: $this->identity($item, $url, $start),
            title: $title,
            startsAt: $start,
            endsAt: $this->parseDate(Arr::get($item, 'endDate')),
            venueName: $this->text(Arr::get($location, 'name')) ?: null,
            address: $this->addressLine($location),
            suburb: $this->text(Arr::get($location, 'address.addressLocality')) ?: null,
            latitude: $this->float(Arr::get($location, 'geo.latitude')),
            longitude: $this->float(Arr::get($location, 'geo.longitude')),
            categorySlug: $source->default_category_slug,
            price: $this->price($item),
            ticketUrl: $this->text(Arr::get($this->firstOf(Arr::get($item, 'offers')), 'url')) ?: $url,
            sourceUrl: $url,
            imageUrl: $this->image($item),
            imageCredit: $source->name,
            sourceDescription: $this->text(Arr::get($item, 'description')) ?: null,
            raw: $item,
        );
    }

    public function requestCount(): int
    {
        return $this->client->requestCount();
    }

    /**
     * Page URLs to visit, from the sitemap where there is one.
     *
     * @return iterable<int, string>
     */
    private function discover(IngestSource $source): iterable
    {
        $allowlist = $source->path_allowlist ?? [];

        if (is_string($source->sitemap_url) && $source->sitemap_url !== '') {
            foreach ($this->sitemapUrls($source->sitemap_url) as $url) {
                if ($this->permitted($url, $allowlist)) {
                    yield $url;
                }
            }

            return;
        }

        if (is_string($source->endpoint) && $source->endpoint !== '') {
            yield $source->endpoint;
        }
    }

    /**
     * @return iterable<int, string>
     */
    private function sitemapUrls(string $sitemapUrl, int $depth = 0): iterable
    {
        // Sitemap indexes point at more sitemaps. One level of nesting is
        // normal; more than two suggests a loop.
        if ($depth > 2) {
            return;
        }

        $response = $this->client->get($sitemapUrl);

        if ($response === null || ! $response->successful()) {
            return;
        }

        $xml = @simplexml_load_string($response->body());

        if ($xml === false) {
            return;
        }

        $isIndex = $xml->getName() === 'sitemapindex';

        foreach ($xml->children() as $node) {
            $location = trim((string) ($node->loc ?? ''));

            if ($location === '') {
                continue;
            }

            if ($isIndex) {
                yield from $this->sitemapUrls($location, $depth + 1);

                continue;
            }

            yield $location;
        }
    }

    /**
     * Honour the source's own allowlist.
     *
     * Sydney Opera House, for instance, disallows everything then permits a
     * named set of sections; a crawl that ignored that would be unwelcome even
     * though each individual page returns happily.
     *
     * @param  array<int, string>  $allowlist
     */
    private function permitted(string $url, array $allowlist): bool
    {
        if ($allowlist === []) {
            return true;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        foreach ($allowlist as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull every Event object out of a page's JSON-LD.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractEvents(string $html): array
    {
        $events = [];

        try {
            $crawler = new Crawler($html);
            $blocks = $crawler->filter('script[type="application/ld+json"]');
        } catch (Throwable) {
            return [];
        }

        foreach ($blocks as $block) {
            $decoded = json_decode((string) $block->textContent, true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->flattenGraph($decoded) as $node) {
                if ($this->isEvent($node)) {
                    $events[] = $node;
                }
            }
        }

        return $events;
    }

    /**
     * JSON-LD arrives as a single object, a list, or an @graph. Flatten all
     * three into one list of nodes.
     *
     * @param  array<mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function flattenGraph(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return array_values(array_filter($data['@graph'], 'is_array'));
        }

        if (array_is_list($data)) {
            $nodes = [];

            foreach ($data as $entry) {
                if (is_array($entry)) {
                    $nodes = array_merge($nodes, $this->flattenGraph($entry));
                }
            }

            return $nodes;
        }

        return [$data];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isEvent(array $node): bool
    {
        $type = Arr::get($node, '@type');
        $types = is_array($type) ? $type : [$type];

        foreach ($types as $candidate) {
            // Covers MusicEvent, TheaterEvent, ComedyEvent, Festival and the rest.
            if (is_string($candidate) && Str::endsWith($candidate, 'Event')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function identity(array $item, string $url, CarbonImmutable $start): string
    {
        $id = Arr::get($item, '@id');

        if (is_string($id) && $id !== '') {
            return Str::limit($id, 180, '');
        }

        return Str::limit($url, 160, '').'#'.$start->format('Y-m-d');
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, 'Australia/Sydney');
        } catch (Throwable) {
            return null;
        }

        // A bare date with no time parses to midnight, which would read as a
        // 12am gig. Treat it as an evening event instead.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1) {
            $date = $date->setTime(19, 0);
        }

        return $date;
    }

    /**
     * @param  array<string, mixed>|null  $location
     */
    private function addressLine(?array $location): ?string
    {
        $address = Arr::get($location ?? [], 'address');

        if (is_string($address)) {
            return $address;
        }

        $street = $this->text(Arr::get($location ?? [], 'address.streetAddress'));

        return $street !== '' ? $street : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function price(array $item): ?string
    {
        $offer = $this->firstOf(Arr::get($item, 'offers'));

        if ($offer === null) {
            return null;
        }

        $low = $this->float(Arr::get($offer, 'lowPrice') ?? Arr::get($offer, 'price'));
        $high = $this->float(Arr::get($offer, 'highPrice'));

        if ($low === null) {
            return null;
        }

        if ($low <= 0.0) {
            return 'Free';
        }

        $format = fn (float $v): string => '$'.(fmod($v, 1.0) === 0.0 ? number_format($v, 0) : number_format($v, 2));

        return $high !== null && $high > $low
            ? $format($low).' – '.$format($high)
            : $format($low);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function image(array $item): ?string
    {
        $image = Arr::get($item, 'image');

        if (is_string($image)) {
            return $image;
        }

        if (is_array($image)) {
            $first = $image[0] ?? $image;

            if (is_string($first)) {
                return $first;
            }

            $url = Arr::get(is_array($first) ? $first : [], 'url');

            return is_string($url) ? $url : null;
        }

        return null;
    }

    /**
     * Schema.org properties are routinely either an object or a list of them.
     *
     * @return array<string, mixed>|null
     */
    private function firstOf(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            $first = $value[0] ?? null;

            return is_array($first) ? $first : null;
        }

        return $value;
    }

    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value))));
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
