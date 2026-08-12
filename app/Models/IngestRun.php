<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IngestRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One execution of one source. Kept so that "why did nothing appear last
 * night?" has an answer in the admin rather than in the logs.
 */
class IngestRun extends Model
{
    /** @use HasFactory<IngestRunFactory> */
    use HasFactory;

    public const STATUSES = ['running', 'success', 'partial', 'failed'];

    protected $fillable = [
        'ingest_source_id',
        'started_at',
        'finished_at',
        'status',
        'items_seen',
        'items_created',
        'items_updated',
        'items_skipped',
        'items_failed',
        'requests_made',
        'error',
        'duration_ms',
        'dry_run',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'dry_run' => 'boolean',
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
     * @return HasMany<EventImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(EventImport::class);
    }

    /**
     * @param  Builder<IngestRun>  $query
     */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('started_at');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /**
     * A run that saw items but failed on some of them is worth surfacing
     * differently from one that fell over entirely.
     */
    public function resolveStatus(): string
    {
        if ($this->items_failed > 0 && $this->items_failed < $this->items_seen) {
            return 'partial';
        }

        if ($this->items_failed > 0 && $this->items_seen === $this->items_failed) {
            return 'failed';
        }

        return 'success';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source?->name,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_ms' => $this->duration_ms,
            'items_seen' => $this->items_seen,
            'items_created' => $this->items_created,
            'items_updated' => $this->items_updated,
            'items_skipped' => $this->items_skipped,
            'items_failed' => $this->items_failed,
            'requests_made' => $this->requests_made,
            'dry_run' => $this->dry_run,
            'error' => $this->error,
        ];
    }
}
