<?php

declare(strict_types=1);
use App\Services\Ingest\Adapters\JsonLdAdapter;
use App\Services\Ingest\Adapters\TicketmasterAdapter;

/*
 * Ingestion settings for the Keep Sydney Live event pipeline.
 *
 * The source registry itself lives in the database (`ingest_sources`) so that
 * adding a venue is an admin form, not a deploy. This file holds the things
 * that are genuinely code-adjacent: adapter wiring, crawl manners, the
 * copywriting model and the rules that keep us on the right side of copyright.
 *
 * Deployment notes (Laravel Cloud):
 *   - Enable the scheduler; bootstrap/app.php declares the entries.
 *   - Run workers for three queues: `ingest` (crawling), `ingest-ai`
 *     (copywriting) and `ingest-media` (artwork). One process each is plenty.
 *   - Set FILESYSTEM_DISK=s3. Container storage is ephemeral, so imported
 *     artwork must go to a bucket or it disappears on the next deploy.
 *   - Set INGEST_CACHE_STORE to the Redis/KV store so the per-host crawl
 *     throttle is shared between workers rather than per container.
 */

return [

    /*
     * Adapter key => class. The `adapter` column on ingest_sources stores the
     * key; anything not listed here is rejected by validation, so a bad admin
     * entry can never resolve to an arbitrary class.
     */
    'adapters' => [
        'ticketmaster' => TicketmasterAdapter::class,
        'json-ld' => JsonLdAdapter::class,
    ],

    /*
     * How we behave on someone else's server. These are deliberately
     * conservative: we are a guest, and a polite guest gets to keep visiting.
     */
    'http' => [
        'user_agent' => env(
            'INGEST_USER_AGENT',
            'KeepSydneyLive/1.0 (+https://kslive.au/bot; events indexer)',
        ),
        'timeout' => 15,
        'connect_timeout' => 8,
        'retries' => 3,
        'retry_delay_ms' => 1000,

        // Per-host politeness. Nominatim's usage policy is the strictest thing
        // we talk to at one request per second, so it sets the floor.
        'requests_per_minute' => 30,
        'min_interval_ms' => 1100,

        // Refuse to buffer a response larger than this. Guards against a
        // misconfigured source URL pointing at something enormous.
        'max_response_bytes' => 5 * 1024 * 1024,

        // How long a parsed robots.txt stays cached per host.
        'robots_cache_minutes' => 1440,

        /*
         * The store holding the per-host throttle and the robots cache.
         *
         * This MUST be shared across every process that crawls. On a single
         * box the default store is fine, but where queue workers run in
         * separate containers a per-container store would give each worker its
         * own idea of when it last called a publisher — and n workers would
         * hit them n times faster than we promised. Point this at Redis in
         * that case (on Laravel Cloud, the managed KV store).
         */
        'cache_store' => env('INGEST_CACHE_STORE'),
    ],

    /*
     * Copywriting. We never republish someone else's prose, so every ingested
     * event gets original copy generated from the extracted facts.
     *
     * Groq speaks the OpenAI wire format, which means this same driver also
     * points at OpenRouter or a local Ollama by changing `base_url` alone.
     */
    'ai' => [
        'enabled' => env('INGEST_AI_ENABLED', true),
        'driver' => env('INGEST_AI_DRIVER', 'groq'),
        'base_url' => env('INGEST_AI_BASE_URL', 'https://api.groq.com/openai/v1'),
        'key' => env('GROQ_API_KEY'),

        // Quality model for featured/imminent events, fast model for the long
        // tail. The free tier is capped on requests per day rather than
        // tokens, so the cheap model is about headroom, not cost.
        'model_primary' => env('INGEST_AI_MODEL_PRIMARY', 'llama-3.3-70b-versatile'),
        'model_bulk' => env('INGEST_AI_MODEL_BULK', 'llama-3.1-8b-instant'),

        'temperature' => 0.7,
        'timeout' => 30,

        // Events per request. Batching is the single biggest lever we have
        // against the daily request cap.
        'batch_size' => 10,

        // Hard ceiling on requests per calendar day, tracked in the cache.
        // Leaves room for the pipeline to degrade to template copy rather than
        // stall when the quota is gone.
        'daily_request_budget' => env('INGEST_AI_DAILY_BUDGET', 800),

        'max_words' => 60,
        'min_words' => 30,
        'max_meta_description' => 155,
    ],

    /*
     * Hero images are the sharpest copyright edge in the whole pipeline, so
     * permission is per source tier rather than a global switch.
     *
     *   licensed - an API whose terms grant us display rights.
     *   verified - a venue or promoter publishing their own event.
     *   signal   - an editorial lister. Facts and a citation only, never art.
     */
    'images' => [
        'allowed_trust' => ['licensed', 'verified'],
        'max_bytes' => 8 * 1024 * 1024,
        'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'directory' => 'events',
    ],

    /*
     * Runtime geocoding, carried over from scripts/geocode_seed.py. Nominatim
     * asks for one request per second and a contactable user agent; both are
     * enforced in PoliteClient.
     */
    'geocoding' => [
        'enabled' => env('INGEST_GEOCODING_ENABLED', true),
        'endpoint' => 'https://nominatim.openstreetmap.org/search',
        'viewbox' => '150.5,-34.3,151.6,-33.4',
        'cache_days' => 90,
    ],

    /*
     * When two sources describe the same event, the one earliest in this list
     * wins the contested fields. Editorial listers never overwrite a ticketing
     * record; they only ever contribute a citation.
     */
    'source_precedence' => [
        'ticketmaster',
        'eventfinda',
        'atdw',
        'whats-on-sydney',
        'venue-direct',
        'editorial',
    ],

    'matching' => [
        // Titles at or above this similarity, at the same venue within the
        // date window, are treated as the same event.
        'title_similarity' => 0.82,
        'date_window_hours' => 24,

        // Below this the import is held for human review no matter what the
        // source's auto_publish setting says.
        'auto_publish_confidence' => 0.9,
    ],

    'retention' => [
        'runs_days' => 90,
        'resolved_imports_days' => 30,
        'past_events_days' => 365,
    ],

    /*
     * Safety rail for the scheduler: no source may be polled more often than
     * this, whatever the admin types into the frequency field.
     */
    'min_frequency_minutes' => 30,
];
