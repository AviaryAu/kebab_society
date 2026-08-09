<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Declarative description of a Kebab Society leaderboard.
 *
 * New boards are added by registering another definition rather than by
 * writing another ranking page.
 */
final readonly class LeaderboardDefinition
{
    public function __construct(
        public string $key,
        public string $title,
        public string $tagline,
        public ?string $styleSlug = null,
        public bool $lateNightOnly = false,
        public bool $societyApprovedOnly = false,
        public int $minimumScore = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'tagline' => $this->tagline,
        ];
    }
}
