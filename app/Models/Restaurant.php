<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\OpeningHoursCast;
use App\Enums\DataSource;
use App\Enums\RestaurantStatus;
use App\Enums\VerificationStatus;
use App\Support\OpeningHours;
use App\Support\ScoreTier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Mean radius of the Earth in kilometres, used for distance sorting. */
    private const EARTH_RADIUS_KM = 6371;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'address_line',
        'suburb_id',
        'postcode',
        'latitude',
        'longitude',
        'phone',
        'website',
        'google_place_id',
        'google_rating',
        'google_review_count',
        'google_data_updated_at',
        'opening_hours',
        'price_level',
        'status',
        'kebab_score',
        'score_breakdown',
        'editorial_adjustment',
        'editorial_note',
        'society_rating',
        'society_review_count',
        'check_in_count',
        'verification_status',
        'society_approved_at',
        'data_source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'google_rating' => 'float',
            'google_review_count' => 'integer',
            'google_data_updated_at' => 'immutable_datetime',
            'opening_hours' => OpeningHoursCast::class,
            'price_level' => 'integer',
            'status' => RestaurantStatus::class,
            'kebab_score' => 'integer',
            'score_breakdown' => 'array',
            'editorial_adjustment' => 'integer',
            'society_rating' => 'float',
            'society_review_count' => 'integer',
            'check_in_count' => 'integer',
            'verification_status' => VerificationStatus::class,
            'society_approved_at' => 'immutable_datetime',
            'data_source' => DataSource::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Suburb, $this>
     */
    public function suburb(): BelongsTo
    {
        return $this->belongsTo(Suburb::class);
    }

    /**
     * @return BelongsToMany<KebabStyle, $this>
     */
    public function kebabStyles(): BelongsToMany
    {
        return $this->belongsToMany(KebabStyle::class)
            ->withPivot('is_signature')
            ->orderBy('sort_order');
    }

    /*
    |---------------------------------------------------------------------------
    | Derived state
    |---------------------------------------------------------------------------
    */

    public function hours(): OpeningHours
    {
        return $this->opening_hours instanceof OpeningHours
            ? $this->opening_hours
            : OpeningHours::fromArray(null);
    }

    public function isOpenAt(?CarbonInterface $moment = null): bool
    {
        if ($this->status === RestaurantStatus::PermanentlyClosed
            || $this->status === RestaurantStatus::TemporarilyClosed) {
            return false;
        }

        return $this->hours()->isOpenAt($moment ?? CarbonImmutable::now());
    }

    public function closingTime(?CarbonInterface $moment = null): ?CarbonImmutable
    {
        return $this->hours()->closingTime($moment ?? CarbonImmutable::now());
    }

    public function nextOpeningTime(?CarbonInterface $moment = null): ?CarbonImmutable
    {
        return $this->hours()->nextOpeningTime($moment ?? CarbonImmutable::now());
    }

    public function tradesLateNight(): bool
    {
        return $this->hours()->tradesLateNight();
    }

    public function scoreTier(): ScoreTier
    {
        return ScoreTier::forScore($this->kebab_score);
    }

    public function isSocietyApproved(): bool
    {
        return $this->society_approved_at !== null;
    }

    /**
     * Great-circle distance to a point, in kilometres.
     */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $latitudeDelta = deg2rad($latitude - $this->latitude);
        $longitudeDelta = deg2rad($longitude - $this->longitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /*
    |---------------------------------------------------------------------------
    | Query scopes
    |---------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Restaurant>  $query
     */
    public function scopeDiscoverable(Builder $query): void
    {
        $query->whereIn('status', [
            RestaurantStatus::Published->value,
            RestaurantStatus::TemporarilyClosed->value,
        ]);
    }

    /**
     * @param  Builder<Restaurant>  $query
     */
    public function scopeSocietyApproved(Builder $query): void
    {
        $query->whereNotNull('society_approved_at');
    }

    /**
     * @param  Builder<Restaurant>  $query
     */
    public function scopeMinimumScore(Builder $query, int $score): void
    {
        $query->where('kebab_score', '>=', $score);
    }

    /**
     * Free-text search across restaurant name, suburb and street address.
     *
     * @param  Builder<Restaurant>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('address_line', 'like', $like)
                ->orWhere('postcode', 'like', $like)
                ->orWhereHas('suburb', fn (Builder $suburb) => $suburb->where('name', 'like', $like));
        });
    }

    /**
     * @param  Builder<Restaurant>  $query
     * @param  array<int, string>  $slugs
     */
    public function scopeWithAnyStyle(Builder $query, array $slugs): void
    {
        if ($slugs === []) {
            return;
        }

        $query->whereHas('kebabStyles', fn (Builder $style) => $style->whereIn('slug', $slugs));
    }
}
