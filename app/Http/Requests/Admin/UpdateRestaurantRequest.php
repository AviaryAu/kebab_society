<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RestaurantStatus;
use App\Enums\VerificationStatus;
use App\Support\OpeningHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $restaurantId = $this->route('restaurant')->id;
        $days = implode(',', OpeningHours::DAYS);

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash', Rule::unique('restaurants', 'slug')->ignore($restaurantId)],
            'description' => ['nullable', 'string', 'max:2000'],

            'address_line' => ['required', 'string', 'max:200'],
            'suburb_id' => ['required', 'integer', 'exists:suburbs,id'],
            'postcode' => ['required', 'digits:4'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],

            'google_place_id' => ['nullable', 'string', 'max:255', Rule::unique('restaurants', 'google_place_id')->ignore($restaurantId)],
            'google_rating' => ['nullable', 'numeric', 'between:0,5'],
            'google_review_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],

            'price_level' => ['nullable', 'integer', 'between:1,4'],
            'status' => ['required', Rule::enum(RestaurantStatus::class)],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'society_approved' => ['required', 'boolean'],

            'editorial_adjustment' => ['required', 'numeric', 'between:-0.5,0.5'],
            'editorial_note' => ['nullable', 'string', 'max:500'],

            'styles' => ['array'],
            'styles.*' => ['integer', 'exists:kebab_styles,id'],

            'opening_hours' => ['array'],
            "opening_hours.*" => ['array', 'max:3'],
            'opening_hours.*.*.open' => ['required', 'date_format:H:i'],
            'opening_hours.*.*.close' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'editorial_adjustment.between' => 'The Society may nudge a rating by half a star at most.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $hours = $this->input('opening_hours', []);

        if (! is_array($hours)) {
            return;
        }

        // Drop any day whose sessions were cleared in the editor.
        $this->merge([
            'opening_hours' => collect($hours)
                ->only(OpeningHours::DAYS)
                ->map(fn ($sessions) => is_array($sessions) ? array_values(array_filter(
                    $sessions,
                    fn ($session) => is_array($session) && ! empty($session['open']) && ! empty($session['close']),
                )) : [])
                ->filter(fn (array $sessions): bool => $sessions !== [])
                ->all(),
        ]);
    }
}
