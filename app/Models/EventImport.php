<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\EventImportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staged event, after extraction and before publication.
 *
 * This is also where third-party prose is quarantined: `raw_payload` may hold
 * a publisher's description because the copywriter needs context, but nothing
 * in here is rendered to the public. Only generated copy reaches an Event.
 */
class EventImport extends Model
{
    /** @use HasFactory<EventImportFactory> */
    use HasFactory;

    protected $fillable = [
        'ingest_source_id',
        'ingest_run_id',
        'external_id',
        'source_url',
        'fingerprint',
        'raw_payload',
        'normalised',
        'proposed_title',
        'proposed_start',
        'proposed_venue_name',
        'proposed_suburb',
        'event_id',
        'match_confidence',
        'status',
        'message',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'raw_payload' => 'array',
            'normalised' => 'array',
            'proposed_start' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'match_confidence' => 'float',
        ];
    }

    /**
     * @return BelongsTo<IngestSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(IngestSource::class, 'ingest_source_id');
    }

    /**
     * @return BelongsTo<IngestRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(IngestRun::class, 'ingest_run_id');
    }

    /**
     * The event this import created or was folded into.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<EventImport>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ImportStatus::Pending);
    }

    /**
     * @param  Builder<EventImport>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('proposed_title', 'like', "%{$term}%")
                ->orWhere('proposed_venue_name', 'like', "%{$term}%")
                ->orWhere('proposed_suburb', 'like', "%{$term}%");
        });
    }

    /**
     * The shape the review queue consumes. Deliberately excludes raw_payload:
     * the list view has no business shipping a publisher's copy to the browser.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source?->name,
            'source_slug' => $this->source?->slug,
            'title' => $this->proposed_title,
            'venue' => $this->proposed_venue_name,
            'suburb' => $this->proposed_suburb,
            'start' => $this->proposed_start?->toIso8601String(),
            'day' => $this->proposed_start?->format('D j M'),
            'time' => $this->proposed_start?->format('g:i A'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'confidence' => $this->match_confidence,
            'event_id' => $this->event_id,
            'source_url' => $this->source_url,
            'message' => $this->message,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
