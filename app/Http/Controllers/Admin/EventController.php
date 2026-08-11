<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Models\Event;
use App\Models\Venue;
use App\Support\HtmlSanitiser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const PER_PAGE = 25;

    private const SORTABLE = ['title', 'start_datetime', 'suburb', 'status', 'updated_at'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:published,draft'],
            'category' => ['nullable', 'string', 'max:60'],
            'when' => ['nullable', 'string', 'in:upcoming,past'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'start_datetime';
        $direction = $filters['direction'] ?? 'asc';

        $events = Event::query()
            ->with('venue:id,name,slug')
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->matching($term))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->where('category_slug', $slug))
            ->when(
                ($filters['when'] ?? null) === 'upcoming',
                fn (Builder $query) => $query->where('start_datetime', '>=', now()),
            )
            ->when(
                ($filters['when'] ?? null) === 'past',
                fn (Builder $query) => $query->where('start_datetime', '<', now()),
            )
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events->through(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'venue' => $event->venue?->name,
                'suburb' => $event->suburb,
                'category' => $event->categoryName(),
                'status' => $event->status,
                'featured' => $event->featured,
                'starts' => $event->start_datetime?->format('D j M Y, g:i A'),
                'is_past' => $event->start_datetime?->isPast() ?? false,
                'edit_url' => route('admin.events.edit', $event->slug),
                'public_url' => "/events/{$event->slug}",
                'updated_at' => $event->updated_at?->diffForHumans(),
            ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? null,
                'category' => $filters['category'] ?? null,
                'when' => $filters['when'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'total' => Event::query()->count(),
                'published' => Event::query()->where('status', 'published')->count(),
                'drafts' => Event::query()->where('status', 'draft')->count(),
                'upcoming' => Event::query()->where('start_datetime', '>=', now())->count(),
            ],
            'categories' => config('kslive.categories'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Events/Form', [
            'event' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = Event::query()->create($this->payload($request));

        return redirect()
            ->route('admin.events.edit', $event->slug)
            ->with('message', "{$event->title} created.");
    }

    public function edit(Event $event): Response
    {
        return Inertia::render('Admin/Events/Form', [
            'event' => [
                ...$event->only([
                    'id', 'title', 'slug', 'description', 'body', 'suburb', 'category_slug',
                    'image', 'price', 'ticket_url', 'status', 'meta_title', 'meta_description',
                ]),
                'venue_id' => $event->venue_id,
                'start_datetime' => $event->start_datetime?->format('Y-m-d\TH:i'),
                'end_datetime' => $event->end_datetime?->format('Y-m-d\TH:i'),
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'featured' => $event->featured,
                'public_url' => "/events/{$event->slug}",
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $event->update($this->payload($request));

        return redirect()
            ->route('admin.events.edit', $event->slug)
            ->with('message', "{$event->title} updated.");
    }

    public function destroy(Event $event): RedirectResponse
    {
        $title = $event->title;
        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('message', "{$title} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EventRequest $request): array
    {
        $data = $request->validated();
        $data['body'] = HtmlSanitiser::clean($data['body'] ?? null);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'statuses' => Event::STATUSES,
            'categories' => config('kslive.categories'),
            'venues' => Venue::query()
                ->orderBy('name')
                ->get(['id', 'name', 'suburb', 'latitude', 'longitude'])
                ->all(),
            'suburbs' => collect(config('kslive.neighbourhoods'))
                ->map(fn (string $slug): string => str($slug)->replace('-', ' ')->title()->replace('Cbd', 'CBD')->value())
                ->values()
                ->all(),
        ];
    }
}
