<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantPreviewResource;
use App\Services\KebabRankingService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaderboardController extends Controller
{
    public function __construct(private readonly KebabRankingService $ranking) {}

    public function show(?string $board = null): Response
    {
        $definitions = $this->ranking->definitions();
        $definition = $board === null
            ? $definitions->first()
            : $this->ranking->find($board);

        if ($definition === null) {
            throw new NotFoundHttpException;
        }

        $entries = $this->ranking->entries($definition);

        return Inertia::render('Leaderboard/Show', [
            'board' => $definition->toArray(),
            'boards' => $definitions->map->toArray()->values(),
            'entries' => $entries->map(fn (array $entry): array => [
                'rank' => $entry['rank'],
                'restaurant' => new RestaurantPreviewResource($entry['restaurant']),
            ])->values(),
        ]);
    }
}
