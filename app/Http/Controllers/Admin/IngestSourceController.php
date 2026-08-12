<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SourceTier;
use App\Enums\SourceTrust;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IngestSourceRequest;
use App\Jobs\Ingest\RunSourceJob;
use App\Models\IngestRun;
use App\Models\IngestSource;
use App\Services\Ingest\AdapterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngestSourceController extends Controller
{
    private const PER_PAGE = 25;

    private const SORTABLE = ['name', 'tier', 'trust', 'last_run_at', 'updated_at'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'tier' => ['nullable', 'string', 'in:'.implode(',', SourceTier::values())],
            'trust' => ['nullable', 'string', 'in:'.implode(',', SourceTrust::values())],
            'health' => ['nullable', 'string', 'in:healthy,failing,disabled'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $sources = IngestSource::query()
            ->withCount('imports')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $term) => $query->where('name', 'like', "%{$term}%"),
            )
            ->when($filters['tier'] ?? null, fn (Builder $query, string $tier) => $query->where('tier', $tier))
            ->when($filters['trust'] ?? null, fn (Builder $query, string $trust) => $query->where('trust', $trust))
            ->when(
                ($filters['health'] ?? null) === 'failing',
                fn (Builder $query) => $query->where('consecutive_failures', '>=', 3),
            )
            ->when(
                ($filters['health'] ?? null) === 'disabled',
                fn (Builder $query) => $query->where('is_enabled', false),
            )
            ->when(
                ($filters['health'] ?? null) === 'healthy',
                fn (Builder $query) => $query->where('is_enabled', true)->where('consecutive_failures', '<', 3),
            )
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Sources/Index', [
            'sources' => $sources->through(fn (IngestSource $source): array => $this->row($source)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tier' => $filters['tier'] ?? null,
                'trust' => $filters['trust'] ?? null,
                'health' => $filters['health'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'total' => IngestSource::query()->count(),
                'enabled' => IngestSource::query()->where('is_enabled', true)->count(),
                'failing' => IngestSource::query()->where('consecutive_failures', '>=', 3)->count(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Sources/Form', [
            'source' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(IngestSourceRequest $request): RedirectResponse
    {
        $source = IngestSource::query()->create($request->validated());

        return redirect()
            ->route('admin.sources.edit', $source->slug)
            ->with('success', "{$source->name} added.");
    }

    public function edit(IngestSource $source): Response
    {
        return Inertia::render('Admin/Sources/Form', [
            'source' => $this->detail($source),
            'runs' => $source->runs()->recent()->limit(20)->get()
                ->map(fn (IngestRun $run): array => $run->toAdminArray()),
            'options' => $this->options(),
        ]);
    }

    public function update(IngestSourceRequest $request, IngestSource $source): RedirectResponse
    {
        $data = $request->validated();

        // An empty credentials field means "leave them alone", not "erase
        // them" — the form never receives the stored values to send back.
        if (blank($data['credentials'] ?? null)) {
            unset($data['credentials']);
        }

        $source->update($data);

        return back()->with('success', "{$source->name} updated.");
    }

    public function destroy(IngestSource $source): RedirectResponse
    {
        $name = $source->name;
        $source->delete();

        return redirect()
            ->route('admin.sources.index')
            ->with('success', "{$name} removed.");
    }

    /**
     * Queue a run.
     *
     * A polite crawl sleeps between requests, so even a modest source takes
     * minutes — far longer than a web request may reasonably last. The button
     * therefore reports that work has started, and the run history below the
     * form is where the result appears.
     */
    public function run(Request $request, IngestSource $source): RedirectResponse
    {
        $options = $request->validate([
            'dry_run' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $dryRun = (bool) ($options['dry_run'] ?? false);

        RunSourceJob::dispatch(
            $source->id,
            $options['limit'] ?? 200,
            $dryRun,
        );

        return back()->with('success', sprintf(
            '%s queued%s. Results appear in the run history when it finishes.',
            $source->name,
            $dryRun ? ' as a dry run' : '',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(IngestSource $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'slug' => $source->slug,
            'adapter' => $source->adapter,
            'tier' => $source->tier->label(),
            'trust' => $source->trust->label(),
            'trust_value' => $source->trust->value,
            'is_enabled' => $source->is_enabled,
            'auto_publish' => $source->mayAutoPublish(),
            'imports_images' => $source->mayImportImages(),
            'frequency_minutes' => $source->frequency_minutes,
            'last_run_at' => $source->last_run_at?->diffForHumans(),
            'last_status' => $source->last_status,
            'last_message' => $source->last_message,
            'consecutive_failures' => $source->consecutive_failures,
            'is_healthy' => $source->isHealthy(),
            'imports_count' => $source->imports_count ?? 0,
            'edit_url' => route('admin.sources.edit', $source->slug),
            'run_url' => route('admin.sources.run', $source->slug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(IngestSource $source): array
    {
        return array_merge($this->row($source), [
            'tier' => $source->tier->value,
            'trust' => $source->trust->value,
            'endpoint' => $source->endpoint,
            'sitemap_url' => $source->sitemap_url,
            'website' => $source->website,
            'path_allowlist' => $source->path_allowlist ?? [],
            'options' => $source->options ?? [],
            'category_map' => $source->category_map ?? [],
            'default_category_slug' => $source->default_category_slug,
            'rate_limit_per_minute' => $source->rate_limit_per_minute,
            'allow_image_import' => $source->allow_image_import,
            'licence' => $source->licence,
            'terms_url' => $source->terms_url,
            'notes' => $source->notes,

            // Never the values themselves; only whether any are stored.
            'has_credentials' => filled($source->credentials),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'adapters' => app(AdapterFactory::class)->keys(),
            'tiers' => array_map(
                fn (SourceTier $tier): array => ['value' => $tier->value, 'label' => $tier->label()],
                SourceTier::cases(),
            ),
            'trusts' => array_map(
                fn (SourceTrust $trust): array => [
                    'value' => $trust->value,
                    'label' => $trust->label(),
                    'allows_images' => $trust->allowsImageImport(),
                    'allows_auto_publish' => $trust->allowsAutoPublish(),
                ],
                SourceTrust::cases(),
            ),
            'categories' => config('kslive.categories'),
            'min_frequency_minutes' => (int) config('ingest.min_frequency_minutes', 30),
        ];
    }
}
