<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RestaurantPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RestaurantPhoto
 */
class RestaurantPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $urls = $this->urls();

        return [
            'id' => $this->id,
            'thumb' => $urls['thumb'] ?? null,
            'card' => $urls['card'] ?? null,
            'hero' => $urls['hero'] ?? null,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
        ];
    }
}
