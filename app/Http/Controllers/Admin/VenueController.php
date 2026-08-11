<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VenueRequest;
use App\Models\Venue;
use App\Support\HtmlSanitiser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    private const PER_PAGE = 25;

    private const SORTABLE = ['name', 'suburb', 'status', 'updated_at'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:published,draft'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $venues = Venue::query()
            ->withCount('events')
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->matching($term))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Venues/Index', [
            'venues' => $venues->through(fn (Venue $venue): array => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'suburb' => $venue->suburb,
                'status' => $venue->status,
                'featured' => $venue->featured,
                'events_count' => $venue->events_count,
                'has_coordinates' => $venue->latitude !== null && $venue->longitude !== null,
                'edit_url' => route('admin.venues.edit', $venue->slug),
                'public_url' => "/venues/{$venue->slug}",
                'updated_at' => $venue->updated_at?->diffForHumans(),
            ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'total' => Venue::query()->count(),
                'published' => Venue::query()->where('status', 'published')->count(),
                'drafts' => Venue::query()->where('status', 'draft')->count(),
                'featured' => Venue::query()->where('featured', true)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Venues/Form', [
            'venue' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(VenueRequest $request): RedirectResponse
    {
        $venue = Venue::query()->create($this->payload($request));

        return redirect()
            ->route('admin.venues.edit', $venue->slug)
            ->with('message', "{$venue->name} created.");
    }

    public function edit(Venue $venue): Response
    {
        return Inertia::render('Admin/Venues/Form', [
            'venue' => [
                ...$venue->only([
                    'id', 'name', 'slug', 'suburb', 'address', 'description', 'body', 'image',
                    'website', 'social_url', 'phone', 'transport', 'status', 'meta_title', 'meta_description',
                ]),
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'featured' => $venue->featured,
                'public_url' => "/venues/{$venue->slug}",
                'events_count' => $venue->events()->count(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        $venue->update($this->payload($request));

        return redirect()
            ->route('admin.venues.edit', $venue->slug)
            ->with('message', "{$venue->name} updated.");
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $name = $venue->name;
        $venue->delete();

        return redirect()
            ->route('admin.venues.index')
            ->with('message', "{$name} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(VenueRequest $request): array
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
            'statuses' => Venue::STATUSES,
            'suburbs' => Venue::query()
                ->select('suburb')
                ->distinct()
                ->orderBy('suburb')
                ->pluck('suburb')
                ->all(),
        ];
    }
}
