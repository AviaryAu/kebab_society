<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KebabStyle extends Model
{
    /** @use HasFactory<\Database\Factories\KebabStyleFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'group',
        'description',
        'sort_order',
        'is_filterable',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_filterable' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<Restaurant, $this>
     */
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class)->withPivot('is_signature');
    }
}
