<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VenueRequest extends FormRequest
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
        $venue = $this->route('venue');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required', 'string', 'max:180', 'alpha_dash',
                Rule::unique('venues', 'slug')->ignore($venue?->id),
            ],
            'suburb' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:200000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'website' => ['nullable', 'url', 'max:255'],
            'social_url' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'transport' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(Venue::STATUSES)],
            'featured' => ['required', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'featured' => $this->boolean('featured'),
        ]);
    }
}
