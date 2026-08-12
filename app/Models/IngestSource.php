<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceTier;
use App\Enums\SourceTrust;
use Database\Factories\IngestSourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A place we get events from. Sources are rows rather than config so that
 * adding a venue is an administrator's job, not a deployment.
 */
class IngestSource extends Model
{
    /** @use HasFactory<IngestSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'adapter',
        'tier',
        'trust',
        'endpoint',
        'sitemap_url',
        'website',
        'path_allowlist',
        'credentials',
        'options',
        'category_map',
        'default_category_slug',
        'frequency_minutes',
        'rate_limit_per_minute',
        'auto_publish',
        'allow_image_import',
        'is_enabled',
        'licence',
        'terms_url',
        'notes',
    ];

    /**
     * Credentials never belong in a log line or an Inertia prop.
     *
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'tier' => SourceTier::class,
            'trust' => SourceTrust::class,
            'path_allowlist' => 'array',
            'credentials' => 'encrypted:array',
            'options' => 'array',
            'category_map' => 'array',
            'auto_publish' => 'boolean',
            'allow_image_import' => 'boolean',
            'is_enabled' => 'boolean',
            'last_run_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<IngestRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(IngestRun::class);
    }

    /**
     * @return HasMany<EventImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(EventImport::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @param  Builder<IngestSource>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * Sources whose polling interval has elapsed. The floor in config stops an
     * over-eager frequency in the admin form from hammering anyone.
     *
     * @param  Builder<IngestSource>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $floor = (int) config('ingest.min_frequency_minutes', 30);

        $query->enabled()->where(function (Builder $query) use ($floor): void {
            $query->whereNull('last_run_at')
                ->orWhereRaw(
                    'last_run_at <= ?',
                    [Carbon::now()->subMinutes($floor)->toDateTimeString()],
                );
        });
    }

    /**
     * Whether enough time has passed to poll this source again. Done in PHP
     * because the interval is per row.
     */
    public function isDue(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->last_run_at === null) {
            return true;
        }

        $minutes = max(
            (int) $this->frequency_minutes,
            (int) config('ingest.min_frequency_minutes', 30),
        );

        return $this->last_run_at->addMinutes($minutes)->isPast();
    }

    /**
     * Images are gated on the source's trust as well as its own flag, so a
     * mistaken tick in the admin form cannot grant a permission the tier does
     * not carry.
     */
    public function mayImportImages(): bool
    {
        return $this->allow_image_import && $this->trust->allowsImageImport();
    }

    /**
     * Likewise for publishing: editorial listings always face a reviewer.
     */
    public function mayAutoPublish(): bool
    {
        return $this->auto_publish && $this->trust->allowsAutoPublish();
    }

    public function isHealthy(): bool
    {
        return $this->consecutive_failures < 3;
    }
}
