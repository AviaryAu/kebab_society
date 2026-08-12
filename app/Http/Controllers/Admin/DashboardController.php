<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\IngestSource;
use App\Models\Page;
use App\Models\Restaurant;
use App\Models\Venue;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'events' => [
                    'total' => Event::query()->count(),
                    'published' => Event::query()->where('status', 'published')->count(),
                    'upcoming' => Event::query()->where('start_datetime', '>=', now())->count(),
                    'drafts' => Event::query()->where('status', 'draft')->count(),
                ],
                'venues' => [
                    'total' => Venue::query()->count(),
                    'published' => Venue::query()->where('status', 'published')->count(),
                    'drafts' => Venue::query()->where('status', 'draft')->count(),
                    'without_coordinates' => Venue::query()->whereNull('latitude')->count(),
                ],
                'pages' => [
                    'total' => Page::query()->count(),
                    'published' => Page::query()->where('status', 'published')->count(),
                    'drafts' => Page::query()->where('status', 'draft')->count(),
                    'guides' => Page::query()->where('type', 'guide')->count(),
                ],
                'restaurants' => [
                    'total' => Restaurant::query()->count(),
                ],
                'ingest' => [
                    'sources' => IngestSource::query()->where('is_enabled', true)->count(),
                    'pending' => EventImport::query()->where('status', ImportStatus::Pending)->count(),
                    'imported' => Event::query()->whereNotNull('ingest_source_id')->count(),
                    'failing' => IngestSource::query()->where('consecutive_failures', '>=', 3)->count(),
                ],
            ],
            'upcoming' => Event::query()
                ->with('venue:id,name')
                ->where('start_datetime', '>=', now())
                ->orderBy('start_datetime')
                ->limit(8)
                ->get()
                ->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'venue' => $event->venue?->name,
                    'suburb' => $event->suburb,
                    'status' => $event->status,
                    'starts' => $event->start_datetime?->format('D j M, g:i A'),
                    'edit_url' => route('admin.events.edit', $event->slug),
                ])
                ->all(),
            'needsAttention' => [
                'past_published' => Event::query()
                    ->where('status', 'published')
                    ->where('start_datetime', '<', now()->subDay())
                    ->count(),
                'events_without_venue' => Event::query()->whereNull('venue_id')->count(),
                'draft_pages' => Page::query()->where('status', 'draft')->count(),
                'pending_imports' => EventImport::query()->where('status', ImportStatus::Pending)->count(),
                'failing_sources' => IngestSource::query()->where('consecutive_failures', '>=', 3)->count(),
            ],
        ]);
    }
}
