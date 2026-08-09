<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\OpeningHours;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<OpeningHours, OpeningHours|array<string, mixed>|null>
 */
final class OpeningHoursCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): OpeningHours
    {
        return OpeningHours::fromArray(is_string($value) ? json_decode($value, true) : $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $hours = match (true) {
            $value instanceof OpeningHours => $value,
            is_array($value), $value === null => OpeningHours::fromArray($value),
            default => throw new InvalidArgumentException('Opening hours must be an array or OpeningHours instance.'),
        };

        return [$key => json_encode($hours->toArray(), JSON_THROW_ON_ERROR)];
    }
}
