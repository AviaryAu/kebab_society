<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SourceTier;
use App\Enums\SourceTrust;
use App\Services\Ingest\AdapterFactory;
use App\Services\Ingest\Http\PoliteClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

class IngestSourceRequest extends FormRequest
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
        $source = $this->route('source');
        $categories = collect(config('kslive.categories'))->pluck('slug')->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:120', 'alpha_dash',
                Rule::unique('ingest_sources', 'slug')->ignore($source?->id),
            ],

            // Only adapters we ship. A class name must never arrive by form.
            'adapter' => ['required', 'string', Rule::in(app(AdapterFactory::class)->keys())],
            'tier' => ['required', Rule::in(SourceTier::values())],
            'trust' => ['required', Rule::in(SourceTrust::values())],

            'endpoint' => ['nullable', 'url', 'max:2048'],
            'sitemap_url' => ['nullable', 'url', 'max:2048'],
            'website' => ['nullable', 'url', 'max:2048'],

            'path_allowlist' => ['nullable', 'array', 'max:100'],
            'path_allowlist.*' => ['string', 'max:255'],

            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:512'],

            'options' => ['nullable', 'array'],
            'category_map' => ['nullable', 'array'],
            'category_map.*' => ['string', Rule::in($categories)],
            'default_category_slug' => ['nullable', 'string', Rule::in($categories)],

            'frequency_minutes' => [
                'required', 'integer',
                'min:'.(int) config('ingest.min_frequency_minutes', 30),
                'max:10080',
            ],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:300'],

            'auto_publish' => ['required', 'boolean'],
            'allow_image_import' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],

            'licence' => ['nullable', 'string', 'max:180'],
            'terms_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'auto_publish' => $this->boolean('auto_publish'),
            'allow_image_import' => $this->boolean('allow_image_import'),
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectPermissionsTheTierDoesNotCarry($validator);
            $this->rejectPrivateUrls($validator);
        });
    }

    /**
     * Refuse the setting rather than silently ignoring it later. The model
     * gates these too, but an administrator who ticks "import images" on an
     * editorial source deserves to be told why they cannot.
     */
    private function rejectPermissionsTheTierDoesNotCarry(Validator $validator): void
    {
        $trust = SourceTrust::tryFrom((string) $this->input('trust'));

        if ($trust === null) {
            return;
        }

        if ($this->boolean('allow_image_import') && ! $trust->allowsImageImport()) {
            $validator->errors()->add(
                'allow_image_import',
                "Images cannot be imported from a {$trust->label()} source. Link to it instead.",
            );
        }

        if ($this->boolean('auto_publish') && ! $trust->allowsAutoPublish()) {
            $validator->errors()->add(
                'auto_publish',
                "A {$trust->label()} source must be reviewed before publishing.",
            );
        }
    }

    /**
     * These URLs are handed to an HTTP client on a schedule, so they are
     * exactly the kind of admin input that turns into a server-side request
     * forgery if nobody checks.
     */
    private function rejectPrivateUrls(Validator $validator): void
    {
        foreach (['endpoint', 'sitemap_url', 'website'] as $field) {
            $url = $this->input($field);

            if (! is_string($url) || $url === '') {
                continue;
            }

            try {
                PoliteClient::assertPublicUrl($url);
            } catch (RuntimeException $e) {
                $validator->errors()->add($field, $e->getMessage());
            }
        }
    }
}
