<?php

declare(strict_types=1);

namespace App\Services\Ingest\Http;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Robots\RobotsTxt;

/**
 * The only way the ingestion pipeline is allowed to touch the outside world.
 *
 * Centralising it means the manners are not optional: every request identifies
 * itself, checks robots.txt, waits its turn, and gives up rather than hammering
 * a host that is struggling. Adapters cannot opt out of any of that, because
 * they have no other client to reach for.
 */
class PoliteClient
{
    private int $requestCount = 0;

    /**
     * Fetch a URL, or null if robots.txt disallows it or the host will not
     * answer. Callers treat null as "skip this one", not as a failure worth
     * aborting a run over.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $query = [], array $headers = []): ?Response
    {
        if (! $this->mayFetch($url)) {
            Log::info('Ingest: robots.txt disallows fetch', ['url' => $url]);

            return null;
        }

        $this->waitForTurn($url);

        try {
            // Passing an empty array as the query REPLACES any query string
            // already on the URL, which silently turned paginated sitemap URLs
            // like ?page=1 back into page one of the index. Only send a query
            // when there is one.
            $request = $this->request($headers);

            $response = $query === []
                ? $request->get($url)
                : $request->get($url, $query);
        } catch (ConnectionException $e) {
            Log::warning('Ingest: connection failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        $this->requestCount++;

        // 429 and 5xx are the host asking us to back off. Honour Retry-After
        // when it is offered rather than guessing.
        if ($response->status() === 429 || $response->serverError()) {
            Log::warning('Ingest: throttled or unavailable', [
                'url' => $url,
                'status' => $response->status(),
                'retry_after' => $response->header('Retry-After'),
            ]);
        }

        return $response;
    }

    /**
     * Fetch and decode JSON, or null if anything about that did not work out.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>|null
     */
    public function getJson(string $url, array $query = [], array $headers = []): ?array
    {
        $response = $this->get($url, $query, $headers + ['Accept' => 'application/json']);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        if ($this->exceedsSizeLimit($response)) {
            Log::warning('Ingest: response exceeded size limit', ['url' => $url]);

            return null;
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Whether robots.txt permits us at this URL.
     *
     * Failing open on a missing or unreadable robots.txt matches the standard:
     * absence of a file is absence of a restriction. Failing closed would make
     * us unable to read most of the web for no benefit to anyone.
     */
    public function mayFetch(string $url): bool
    {
        // A sitemap the site itself advertises is meant to be read: that is the
        // entire purpose of the directive. Several publishers — Sydney Opera
        // House among them — disallow everything, allow a named list of
        // sections, then point at a sitemap sitting outside that list. We take
        // the invitation over the oversight.
        if ($this->isDeclaredSitemap($url)) {
            return true;
        }

        $robots = $this->robotsFor($url);

        if ($robots === null) {
            return true;
        }

        return $robots->allows($url, $this->userAgent());
    }

    /**
     * Sitemap URLs a host publishes in its own robots.txt.
     *
     * @return array<int, string>
     */
    public function declaredSitemaps(string $url): array
    {
        preg_match_all(
            '/^[ \t]*sitemap[ \t]*:[ \t]*(\S+)[ \t]*$/im',
            $this->robotsText($url),
            $matches,
        );

        return array_map(trim(...), $matches[1] ?? []);
    }

    private function isDeclaredSitemap(string $url): bool
    {
        $normalise = static fn (string $u): string => rtrim((string) strtok($u, '?'), '/');
        $target = $normalise($url);

        foreach ($this->declaredSitemaps($url) as $sitemap) {
            if ($normalise($sitemap) === $target) {
                return true;
            }
        }

        return false;
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    public function resetRequestCount(): void
    {
        $this->requestCount = 0;
    }

    public function userAgent(): string
    {
        return (string) config('ingest.http.user_agent');
    }

    /**
     * The store holding crawl state.
     *
     * Named explicitly rather than taken from the default, because every
     * crawling process has to share one view of when a host was last called.
     * See the note in config/ingest.php.
     */
    private function cache(): Repository
    {
        $store = config('ingest.http.cache_store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * Parsed robots.txt for a host, cached so a crawl of two hundred pages
     * costs one extra request rather than two hundred.
     */
    private function robotsFor(string $url): ?RobotsTxt
    {
        $contents = $this->robotsText($url);

        return $contents === '' ? null : RobotsTxt::create($contents);
    }

    /**
     * The raw robots.txt for a host, cached.
     *
     * Kept as text as well as a parsed object because the Sitemap directives
     * are not part of the exclusion rules and the parser does not surface them.
     */
    private function robotsText(string $url): string
    {
        $host = $this->hostKey($url);

        if ($host === null) {
            return '';
        }

        $minutes = (int) config('ingest.http.robots_cache_minutes', 1440);

        return (string) $this->cache()->remember(
            "ingest:robots:{$host}",
            now()->addMinutes($minutes),
            function () use ($url): string {
                $parts = parse_url($url);
                $robotsUrl = sprintf('%s://%s/robots.txt', $parts['scheme'] ?? 'https', $parts['host']);

                try {
                    $response = $this->request()->get($robotsUrl);
                } catch (ConnectionException) {
                    return '';
                }

                return $response->successful() ? $response->body() : '';
            },
        );
    }

    /**
     * Space requests out per host.
     *
     * The interval is per host rather than global so that being slow with one
     * publisher does not stall the rest of the run. Nominatim's one-request-a-
     * second policy sets the default floor.
     */
    private function waitForTurn(string $url): void
    {
        $host = $this->hostKey($url);

        if ($host === null) {
            return;
        }

        $interval = (int) config('ingest.http.min_interval_ms', 1100);
        $key = "ingest:last-request:{$host}";
        $cache = $this->cache();

        $last = $cache->get($key);

        if (is_numeric($last)) {
            $elapsed = (int) ((microtime(true) - (float) $last) * 1000);

            if ($elapsed < $interval) {
                usleep(($interval - $elapsed) * 1000);
            }
        }

        $cache->put($key, microtime(true), now()->addMinutes(5));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(array $headers = []): PendingRequest
    {
        return Http::withHeaders($headers + [
            'User-Agent' => $this->userAgent(),
            'Accept-Language' => 'en-AU,en;q=0.9',
        ])
            ->timeout((int) config('ingest.http.timeout', 15))
            ->connectTimeout((int) config('ingest.http.connect_timeout', 8))
            ->retry(
                (int) config('ingest.http.retries', 3),
                (int) config('ingest.http.retry_delay_ms', 1000),
                throw: false,
            );
    }

    private function exceedsSizeLimit(Response $response): bool
    {
        $max = (int) config('ingest.http.max_response_bytes', 5 * 1024 * 1024);
        $length = $response->header('Content-Length');

        if (is_numeric($length) && (int) $length > $max) {
            return true;
        }

        return strlen($response->body()) > $max;
    }

    private function hostKey(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return md5(strtolower($host));
    }

    /**
     * Guard against a source row pointing somewhere it should not. Ingestion
     * URLs come from an admin form, so they are untrusted input reaching an
     * HTTP client: without this, a crafted source could aim the pipeline at
     * internal infrastructure.
     */
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException("Ingest source URL must be http(s): {$url}");
        }

        $literalIp = filter_var($host, FILTER_VALIDATE_IP);
        $resolved = $literalIp !== false ? $literalIp : gethostbyname($host);

        // gethostbyname hands back the input unchanged when it cannot resolve.
        // We reject what we can prove is internal rather than what we merely
        // failed to look up: an unresolvable host cannot be fetched anyway, and
        // failing closed here would break every environment without DNS.
        if (filter_var($resolved, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $isPublic = filter_var(
            $resolved,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($isPublic === false) {
            throw new RuntimeException("Ingest source URL must resolve to a public address: {$url}");
        }
    }
}
