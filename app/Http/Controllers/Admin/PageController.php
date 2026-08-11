<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Support\HtmlSanitiser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    private const PER_PAGE = 25;

    private const SORTABLE = ['title', 'type', 'status', 'sort_order', 'published_at', 'updated_at'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:published,draft'],
            'type' => ['nullable', 'string', 'in:guide,page'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'sort_order';
        $direction = $filters['direction'] ?? 'asc';

        $pages = Page::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->matching($term))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->orderBy($sort, $direction)
            ->orderBy('title')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Pages/Index', [
            'pages' => $pages->through(fn (Page $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'type' => $page->type,
                'status' => $page->status,
                'featured' => $page->featured,
                'sort_order' => $page->sort_order,
                'has_body' => filled($page->body),
                'published_at' => $page->published_at?->format('j M Y'),
                'edit_url' => route('admin.pages.edit', $page->slug),
                'public_url' => $page->publicUrl(),
                'updated_at' => $page->updated_at?->diffForHumans(),
            ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? null,
                'type' => $filters['type'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'total' => Page::query()->count(),
                'published' => Page::query()->where('status', 'published')->count(),
                'drafts' => Page::query()->where('status', 'draft')->count(),
                'guides' => Page::query()->where('type', 'guide')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Form', [
            'page' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = Page::query()->create($this->payload($request));

        return redirect()
            ->route('admin.pages.edit', $page->slug)
            ->with('message', "{$page->title} created.");
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Form', [
            'page' => [
                ...$page->only([
                    'id', 'title', 'slug', 'type', 'excerpt', 'body', 'image',
                    'status', 'sort_order', 'meta_title', 'meta_description',
                ]),
                'featured' => $page->featured,
                'published_at' => $page->published_at?->format('Y-m-d\TH:i'),
                'public_url' => $page->publicUrl(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $page->update($this->payload($request));

        return redirect()
            ->route('admin.pages.edit', $page->slug)
            ->with('message', "{$page->title} updated.");
    }

    public function destroy(Page $page): RedirectResponse
    {
        $title = $page->title;
        $page->delete();

        return redirect()
            ->route('admin.pages.index')
            ->with('message', "{$title} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PageRequest $request): array
    {
        $data = $request->validated();
        $data['body'] = HtmlSanitiser::clean($data['body'] ?? null);

        // Publishing without a date should still read as published today.
        if ($data['status'] === 'published' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'statuses' => Page::STATUSES,
            'types' => Page::TYPES,
            'reserved_slugs' => PageRequest::RESERVED_SLUGS,
        ];
    }
}
