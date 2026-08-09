<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\KebabStyle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KebabStyle
 */
class KebabStyleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'group' => $this->group,
            'description' => $this->description,
            'is_signature' => (bool) ($this->pivot->is_signature ?? false),
        ];
    }
}
