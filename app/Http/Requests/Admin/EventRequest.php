<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
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
        $event = $this->route('event');
        $categories = collect(config('kslive.categories'))->pluck('slug')->all();

        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'required', 'string', 'max:200', 'alpha_dash',
                Rule::unique('events', 'slug')->ignore($event?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:200000'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['nullable', 'date', 'after:start_datetime'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'suburb' => ['required', 'string', 'max:120'],
            'category_slug' => ['required', 'string', Rule::in($categories)],
            'image' => ['nullable', 'string', 'max:2048'],
            'price' => ['nullable', 'string', 'max:60'],
            'ticket_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'featured' => ['required', 'boolean'],
            'status' => ['required', Rule::in(Event::STATUSES)],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
            'featured' => $this->boolean('featured'),
        ]);
    }
}
