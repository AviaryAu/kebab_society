<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Ingest\GenerateEventCopyJob;
use App\Jobs\Ingest\ImportEventImageJob;
use App\Models\EventImport;
use App\Models\IngestSource;
use App\Services\Ingest\EventImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue.
 *
 * Everything from an editorial source lands here, along with anything the
 * matcher was unsure about. It is the one place a person decides whether
 * something we were told about becomes something we publish.
 */
class EventImportController extends Controller
{
    private const PER_PAGE = 25;

    private const SORTABLE = ['proposed_title', 'proposed_start', 'match_confidence', 'created_at'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', ImportStatus::values())],
            'source' => ['nullable', 'string', 'max:120'],
            'confidence' => ['nullable', 'string', 'in:certain,unsure'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'proposed_start';
        $direction = $filters['direction'] ?? 'asc';

        $imports = EventImport::query()
            ->with(['source:id,name,slug,trust', 'event:id,slug,title'])
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->matching($term))
            // Pending by default: the queue is a to-do list, not an archive.
            ->when(
                $filters['status'] ?? null,
                fn (Builder $q, string $status) => $q->where('status', $status),
                fn (Builder $q) => $q->where('status', ImportStatus::Pending),
            )
            ->when(
                $filters['source'] ?? null,
                fn (Builder $q, string $slug) => $q->whereHas('source', fn (Builder $s) => $s->where('slug', $slug)),
            )
            ->when(
                ($filters['confidence'] ?? null) === 'unsure',
                fn (Builder $q) => $q->where('match_confidence', '<', 0.9),
            )
            ->when(
                ($filters['confidence'] ?? null) === 'certain',
                fn (Builder $q) => $q->where('match_confidence', '>=', 0.9),
            )
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Imports/Index', [
            'imports' => $imports->through(fn (EventImport $import): array => $import->toAdminArray()),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? ImportStatus::Pending->value,
                'source' => $filters['source'] ?? null,
                'confidence' => $filters['confidence'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'pending' => EventImport::query()->where('status', ImportStatus::Pending)->count(),
                'approved' => EventImport::query()->whereIn('status', [
                    ImportStatus::Approved->value,
                    ImportStatus::Auto->value,
                ])->count(),
                'rejected' => EventImport::query()->where('status', ImportStatus::Rejected)->count(),
            ],
            'options' => [
                'sources' => IngestSource::query()
                    ->orderBy('name')
                    ->get(['slug', 'name'])
                    ->map(fn (IngestSource $s): array => ['value' => $s->slug, 'label' => $s->name]),
                'statuses' => array_map(
                    fn (ImportStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ],
                    ImportStatus::cases(),
                ),
            ],
        ]);
    }

    public function approve(Request $request, EventImport $import, EventImporter $importer): RedirectResponse
    {
        $event = $importer->publish($import, $request->user()?->id);

        if ($event === null) {
            return back()->with('error', 'That import no longer has usable data.');
        }

        // Copy and artwork follow on the queue, exactly as for an automatic
        // import, so an approved listing is not left bare.
        GenerateEventCopyJob::dispatch([$event->id]);

        if ($import->source?->mayImportImages()) {
            ImportEventImageJob::dispatch($event->id);
        }

        return back()->with('success', "\"{$event->title}\" published as a draft event.");
    }

    public function reject(Request $request, EventImport $import, EventImporter $importer): RedirectResponse
    {
        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ])['reason'] ?? null;

        $importer->reject($import, $request->user()?->id, $reason);

        return back()->with('success', 'Import rejected. It will not be offered again.');
    }

    /**
     * Approve or reject several at once. The queue fills faster than anyone
     * wants to click through it one row at a time.
     */
    public function bulk(Request $request, EventImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $imports = EventImport::query()
            ->with('source')
            ->whereIn('id', $data['ids'])
            ->where('status', ImportStatus::Pending)
            ->get();

        $reviewer = $request->user()?->id;
        $handled = 0;

        foreach ($imports as $import) {
            if ($data['action'] === 'reject') {
                $importer->reject($import, $reviewer);
                $handled++;

                continue;
            }

            $event = $importer->publish($import, $reviewer);

            if ($event === null) {
                continue;
            }

            GenerateEventCopyJob::dispatch([$event->id]);

            if ($import->source?->mayImportImages()) {
                ImportEventImageJob::dispatch($event->id);
            }

            $handled++;
        }

        return back()->with('success', sprintf(
            '%d %s.',
            $handled,
            $data['action'] === 'approve' ? 'published' : 'rejected',
        ));
    }
}
