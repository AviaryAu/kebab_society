<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Enums\ImageLicence;
use App\Models\Event;
use App\Services\Ingest\Http\PoliteClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Copies an event's hero image onto our own storage.
 *
 * Two reasons this is not simply hotlinking. Practically, a source that moves
 * or expires its artwork would leave holes across the site. Legally, taking a
 * copy is exactly the act that needs permission, so it happens only where the
 * source's tier grants it — the gate is checked here as well as on the model,
 * because this is the method that does the copying.
 *
 * Re-encoding to WebP is also a security measure: it discards anything
 * smuggled inside the original file, the same reasoning as MediaController.
 */
class ImageImporter
{
    public function __construct(
        private readonly PoliteClient $client,
        private readonly ImageManager $images,
    ) {}

    /**
     * @return string|null The stored public URL, or null if nothing was taken.
     */
    public function import(Event $event): ?string
    {
        $source = $event->ingestSource;
        $url = $event->image_source_url;

        if ($source === null || ! is_string($url) || $url === '') {
            return null;
        }

        // The permission, restated at the point of copying.
        if (! $source->mayImportImages()) {
            $event->forceFill(['image_licence' => ImageLicence::None])->save();

            return null;
        }

        try {
            PoliteClient::assertPublicUrl($url);
        } catch (RuntimeException $e) {
            Log::warning('Ingest: refused image URL', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        try {
            return $this->fetchAndStore($event, $url);
        } catch (Throwable $e) {
            Log::warning('Ingest: image import failed', [
                'event' => $event->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchAndStore(Event $event, string $url): ?string
    {
        $response = $this->client->get($url);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $contentType = strtolower(Str::before((string) $response->header('Content-Type'), ';'));
        $allowed = (array) config('ingest.images.mime_types', []);

        if ($contentType !== '' && ! in_array(trim($contentType), $allowed, true)) {
            Log::info('Ingest: skipped non-image response', ['url' => $url, 'type' => $contentType]);

            return null;
        }

        $body = $response->body();
        $max = (int) config('ingest.images.max_bytes', 8 * 1024 * 1024);

        if ($body === '' || strlen($body) > $max) {
            return null;
        }

        $config = config('kslive.media');
        $disk = (string) $config['disk'];

        // Decoding is also the validation: anything that is not really an image
        // throws here and is caught by the caller. decodeBinary is deliberate —
        // it treats the payload as bytes and never as a path.
        $image = $this->images
            ->decodeBinary($body)
            ->orient()
            ->scaleDown((int) $config['max_width']);

        $directory = trim((string) config('ingest.images.directory', 'events'), '/');
        $path = $directory.'/'.now()->format('Y/m').'/'.Str::random(20).'.webp';

        Storage::disk($disk)->put(
            $path,
            (string) $image->encode(new WebpEncoder(
                quality: (int) $config['quality'],
                strip: true,
            )),
            'public',
        );

        $stored = Storage::disk($disk)->url($path);

        $previous = $event->image;

        $event->forceFill([
            'image' => $stored,
            'image_licence' => ImageLicence::Licensed,
        ])->save();

        $this->discard($disk, $previous);

        return $stored;
    }

    /**
     * Remove artwork we replaced, so a source that reissues its images does not
     * quietly fill the bucket.
     */
    private function discard(string $disk, ?string $previous): void
    {
        if ($previous === null || $previous === '') {
            return;
        }

        $directory = trim((string) config('ingest.images.directory', 'events'), '/');
        $path = ltrim((string) parse_url($previous, PHP_URL_PATH), '/');

        // Only ever delete from our own ingest directory, never an editor's upload.
        if (! Str::contains($path, $directory.'/')) {
            return;
        }

        $relative = Str::after($path, $directory.'/');

        Storage::disk($disk)->delete($directory.'/'.$relative);
    }
}
