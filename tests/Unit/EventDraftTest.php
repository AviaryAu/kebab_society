<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ingest\EventDraft;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDraftTest extends TestCase
{
    #[Test]
    public function two_sources_describing_the_same_gig_agree_on_a_fingerprint(): void
    {
        $ticketing = $this->draft(
            externalId: 'tm-1',
            title: 'Courtney Barnett',
            venueName: 'Enmore Theatre',
        );

        // Same night, same room, reported by someone else with different
        // casing, punctuation and a start time an hour out.
        $editorial = $this->draft(
            externalId: 'cp-9',
            title: 'courtney  barnett',
            venueName: 'ENMORE THEATRE',
            startsAt: CarbonImmutable::parse('2030-03-04 21:00'),
        );

        $this->assertSame($ticketing->fingerprint(), $editorial->fingerprint());
    }

    #[Test]
    public function a_different_night_is_a_different_event(): void
    {
        $friday = $this->draft();
        $saturday = $this->draft(startsAt: CarbonImmutable::parse('2030-03-05 20:00'));

        $this->assertNotSame($friday->fingerprint(), $saturday->fingerprint());
    }

    #[Test]
    public function facts_exclude_the_publishers_prose(): void
    {
        $draft = $this->draft(
            sourceDescription: 'A gorgeous, aching set from one of Melbourne\'s finest.',
        );

        $facts = $draft->facts();

        $this->assertArrayNotHasKey('description', $facts);
        $this->assertArrayNotHasKey('source_description', $facts);
        $this->assertSame('Courtney Barnett', $facts['title']);
        $this->assertSame('Enmore Theatre', $facts['venue']);
    }

    #[Test]
    public function facts_drop_empty_values_rather_than_passing_blanks_to_the_writer(): void
    {
        $facts = $this->draft(price: null)->facts();

        $this->assertArrayNotHasKey('price', $facts);
    }

    #[Test]
    public function a_finished_event_is_not_upcoming(): void
    {
        $past = $this->draft(startsAt: CarbonImmutable::parse('2020-01-01 20:00'));

        $this->assertFalse($past->isUpcoming());
        $this->assertTrue($this->draft()->isUpcoming());
    }

    #[Test]
    public function an_event_running_late_tonight_still_counts_as_upcoming(): void
    {
        $draft = $this->draft(
            startsAt: CarbonImmutable::now()->subHour(),
            endsAt: CarbonImmutable::now()->addHours(2),
        );

        $this->assertTrue($draft->isUpcoming());
    }

    private function draft(
        string $externalId = 'tm-1',
        string $title = 'Courtney Barnett',
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        ?string $venueName = 'Enmore Theatre',
        ?string $price = '$65',
        ?string $sourceDescription = null,
    ): EventDraft {
        return new EventDraft(
            externalId: $externalId,
            title: $title,
            startsAt: $startsAt ?? CarbonImmutable::parse('2030-03-04 20:00'),
            endsAt: $endsAt,
            venueName: $venueName,
            suburb: 'Newtown',
            categorySlug: 'music',
            price: $price,
            sourceDescription: $sourceDescription,
        );
    }
}
