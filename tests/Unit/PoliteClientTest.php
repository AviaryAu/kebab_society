<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ingest\Http\PoliteClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PoliteClientTest extends TestCase
{
    private PoliteClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // The real interval is a courtesy to other people's servers, not to
        // our test suite.
        config()->set('ingest.http.min_interval_ms', 0);

        $this->client = new PoliteClient;
    }

    #[Test]
    public function it_refuses_a_path_that_robots_disallows(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /private/"),
            '*' => Http::response('should never be reached'),
        ]);

        $this->assertFalse($this->client->mayFetch('https://example.test/private/gig'));
        $this->assertNull($this->client->get('https://example.test/private/gig'));
    }

    #[Test]
    public function it_fetches_a_path_that_robots_allows(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /private/"),
            'example.test/events*' => Http::response('<h1>Gigs</h1>'),
        ]);

        $response = $this->client->get('https://example.test/events');

        $this->assertNotNull($response);
        $this->assertStringContainsString('Gigs', $response->body());
    }

    #[Test]
    public function a_missing_robots_file_is_not_a_prohibition(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response('', 404),
            'example.test/events' => Http::response('ok'),
        ]);

        $this->assertTrue($this->client->mayFetch('https://example.test/events'));
    }

    #[Test]
    public function robots_is_fetched_once_per_host_not_once_per_page(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response("User-agent: *\nAllow: /"),
            'example.test/*' => Http::response('ok'),
        ]);

        $this->client->get('https://example.test/events/one');
        $this->client->get('https://example.test/events/two');
        $this->client->get('https://example.test/events/three');

        $robotsCalls = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/robots.txt'))
            ->count();

        $this->assertSame(1, $robotsCalls);
    }

    #[Test]
    public function it_identifies_itself_with_a_contactable_user_agent(): void
    {
        Http::fake(['*' => Http::response('ok')]);

        $this->client->get('https://example.test/events');

        Http::assertSent(function ($request): bool {
            $agent = $request->header('User-Agent')[0] ?? '';

            return str_contains($agent, 'KeepSydneyLive')
                && str_contains($agent, 'kslive.au');
        });
    }

    #[Test]
    public function it_counts_requests_for_the_run_log(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response(''),
            '*' => Http::response('ok'),
        ]);

        $this->client->get('https://example.test/a');
        $this->client->get('https://example.test/b');

        // robots.txt is our overhead, not the source's item count.
        $this->assertSame(2, $this->client->requestCount());
    }

    #[Test]
    public function it_decodes_json_and_returns_null_on_failure(): void
    {
        Http::fake([
            'example.test/robots.txt' => Http::response(''),
            'example.test/ok' => Http::response(['events' => [['id' => 1]]]),
            'example.test/missing' => Http::response('', 404),
        ]);

        $this->assertSame(
            ['events' => [['id' => 1]]],
            $this->client->getJson('https://example.test/ok'),
        );
        $this->assertNull($this->client->getJson('https://example.test/missing'));
    }

    #[Test]
    public function it_rejects_a_response_larger_than_the_cap(): void
    {
        config()->set('ingest.http.max_response_bytes', 64);

        Http::fake([
            'example.test/robots.txt' => Http::response(''),
            'example.test/huge' => Http::response(['padding' => str_repeat('x', 500)]),
        ]);

        $this->assertNull($this->client->getJson('https://example.test/huge'));
    }

    #[Test]
    public function it_rejects_a_source_url_pointing_at_internal_infrastructure(): void
    {
        $this->expectException(RuntimeException::class);

        PoliteClient::assertPublicUrl('http://127.0.0.1/admin');
    }

    #[Test]
    public function it_rejects_a_private_network_address(): void
    {
        $this->expectException(RuntimeException::class);

        PoliteClient::assertPublicUrl('http://192.168.1.10/events');
    }

    #[Test]
    public function it_rejects_the_cloud_metadata_address(): void
    {
        $this->expectException(RuntimeException::class);

        PoliteClient::assertPublicUrl('http://169.254.169.254/latest/meta-data/');
    }

    #[Test]
    public function it_rejects_a_non_http_scheme(): void
    {
        $this->expectException(RuntimeException::class);

        PoliteClient::assertPublicUrl('file:///etc/passwd');
    }

    #[Test]
    public function it_accepts_a_public_address(): void
    {
        // An IP literal rather than a hostname, so the check does not depend on
        // DNS being available to the test runner.
        PoliteClient::assertPublicUrl('https://93.184.216.34/events');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function an_unresolvable_host_is_not_treated_as_internal(): void
    {
        PoliteClient::assertPublicUrl('https://this-host-does-not-exist.invalid/events');

        $this->expectNotToPerformAssertions();
    }
}
