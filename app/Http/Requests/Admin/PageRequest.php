<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    /**
     * Slugs that already belong to a route. A standalone page cannot claim one.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'events', 'venues', 'locations', 'guides', 'map', 'storage', 'build',
        'music', 'comedy', 'theatre', 'nightlife', 'festivals', 'food', 'arts', 'sport',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'required', 'string', 'max:200', 'alpha_dash',
                Rule::unique('pages', 'slug')->ignore($page?->id),
                Rule::when(
                    $this->input('type') === 'page',
                    [Rule::notIn(self::RESERVED_SLUGS)],
                ),
            ],
            'type' => ['required', Rule::in(Page::TYPES)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:400000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', Rule::in(Page::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.not_in' => 'That slug belongs to a Keep Sydney Live route. Pick another.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
            'featured' => $this->boolean('featured'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }
}
