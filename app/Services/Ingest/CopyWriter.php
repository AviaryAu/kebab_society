<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Models\Event;
use App\Services\Ingest\Exceptions\CopyWriterThrottled;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes original copy for ingested events.
 *
 * Every public sentence on an ingested event comes from here. We take facts
 * from publishers and write our own words about them, which is both the
 * honest arrangement and the one that keeps us clear of their copyright.
 *
 * Groq speaks the OpenAI wire format, so pointing `base_url` at OpenRouter or
 * a local Ollama needs no code change. Its free tier is capped on requests per
 * day rather than tokens, which shapes everything here: events are batched,
 * the budget is counted, and copy is written once and then left alone.
 */
class CopyWriter
{
    /**
     * Write copy for a batch of events, keyed by event id.
     *
     * Any event the model fails to cover falls back to template copy rather
     * than being left blank, so a bad response degrades the writing instead of
     * stalling the pipeline.
     *
     * @param  Collection<int, Event>  $events
     * @return array<int, GeneratedCopy>
     */
    public function generateMany(Collection $events): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        $generated = [];

        foreach ($events->chunk($this->batchSize()) as $chunk) {
            $generated += $this->generateChunk($chunk);
        }

        return $generated;
    }

    public function generate(Event $event): GeneratedCopy
    {
        $copy = $this->generateMany(collect([$event]));

        return $copy[$event->id] ?? $this->template($event);
    }

    /**
     * Copy built from the facts alone. Deliberately plain: it is a placeholder
     * that reads as deliberate, not an imitation of the real thing.
     */
    public function template(Event $event): GeneratedCopy
    {
        $facts = $event->copyFacts();

        $sentence = trim(sprintf(
            '%s at %s%s. %s%s.',
            Arr::get($facts, 'category', 'Live'),
            Arr::get($facts, 'venue', 'a Sydney venue'),
            isset($facts['suburb']) ? ', '.$facts['suburb'] : '',
            Arr::get($facts, 'date', ''),
            isset($facts['start_time']) ? ' from '.$facts['start_time'] : '',
        ));

        return new GeneratedCopy(
            description: $sentence,
            metaDescription: Str::limit($sentence, $this->maxMetaLength() - 1, '…'),
            model: 'template',
        );
    }

    /**
     * Requests left in today's allowance.
     */
    public function budgetRemaining(): int
    {
        $budget = (int) config('ingest.ai.daily_request_budget', 800);

        return max(0, $budget - (int) Cache::get($this->budgetKey(), 0));
    }

    public function isEnabled(): bool
    {
        return (bool) config('ingest.ai.enabled', true)
            && filled(config('ingest.ai.key'));
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, GeneratedCopy>
     */
    private function generateChunk(Collection $events): array
    {
        if (! $this->isEnabled() || $this->budgetRemaining() < 1) {
            return $this->allTemplates($events);
        }

        try {
            $payload = $this->ask($events);
        } catch (CopyWriterThrottled $e) {
            // Let the queue decide what to do about waiting.
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Ingest: copy generation failed', ['error' => $e->getMessage()]);

            return $this->allTemplates($events);
        }

        if ($payload === null) {
            return $this->allTemplates($events);
        }

        return $this->parse($events, $payload);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<string, mixed>|null
     */
    private function ask(Collection $events): ?array
    {
        $model = $this->modelFor($events);

        $this->consumeBudget();

        $response = Http::withToken((string) config('ingest.ai.key'))
            ->timeout((int) config('ingest.ai.timeout', 30))
            ->acceptJson()
            ->post(rtrim((string) config('ingest.ai.base_url'), '/').'/chat/completions', [
                'model' => $model,
                'temperature' => (float) config('ingest.ai.temperature', 0.7),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($events)],
                ],
            ]);

        if ($response->status() === 429) {
            throw new CopyWriterThrottled(
                max(1, (int) ($response->header('retry-after') ?: 60)),
            );
        }

        if (! $response->successful()) {
            Log::warning('Ingest: copy provider returned an error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]);

            return null;
        }

        $content = Arr::get($response->json(), 'choices.0.message.content');

        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? ['model' => $model, 'data' => $decoded] : null;
    }

    /**
     * The voice, and the rules that keep the output honest.
     */
    private function systemPrompt(): string
    {
        $min = (int) config('ingest.ai.min_words', 30);
        $max = (int) config('ingest.ai.max_words', 60);
        $meta = $this->maxMetaLength();

        return <<<PROMPT
        You write listings for Keep Sydney Live, an independent Sydney events guide.

        Voice: confident, useful, culturally aware, lightly irreverent. Write like a
        switched-on Sydney local publication. Never corporate, childish or breathless.
        No exclamation marks. No "don't miss", "get ready", "immerse yourself" or
        similar promotional filler. Australian spelling.

        You will receive a JSON array of events, each with an "id" and a set of facts.

        Rules:
        - Use ONLY the facts given. Never invent a lineup, a support act, a running
          time, a price, a review or a description of the atmosphere.
        - Do not restate the date and time mechanically; the page already shows them.
        - Do not address the reader as "you". Do not use second person imperatives.
        - Write {$min}-{$max} words for "description": one or two plain sentences about
          what the event is.
        - Write at most {$meta} characters for "meta_description".
        - Plain text only. No markdown, no HTML, no links, no emoji.

        Respond with JSON in exactly this shape and nothing else:
        {"events":[{"id":123,"description":"...","meta_description":"..."}]}
        PROMPT;
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function userPrompt(Collection $events): string
    {
        $payload = $events->map(function (Event $event): array {
            $facts = ['id' => $event->id] + $event->copyFacts();

            // A publisher's own words are only ever context, and only from
            // sources whose terms allow it. Editorial listings contribute
            // facts and nothing else, so their prose never reaches a prompt.
            $trust = $event->ingestSource?->trust;

            if ($trust !== null && $trust->allowsImageImport()) {
                $note = $event->ingestSource?->imports()
                    ->where('event_id', $event->id)
                    ->value('raw_payload');

                $info = is_array($note) ? Arr::get($note, 'info') : null;

                if (is_string($info) && $info !== '') {
                    $facts['organiser_note'] = Str::limit(strip_tags($info), 300);
                }
            }

            return $facts;
        })->values()->all();

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  array<string, mixed>  $payload
     * @return array<int, GeneratedCopy>
     */
    private function parse(Collection $events, array $payload): array
    {
        $model = (string) Arr::get($payload, 'model');
        $rows = Arr::get($payload, 'data.events', []);

        if (! is_array($rows)) {
            return $this->allTemplates($events);
        }

        $byId = $events->keyBy('id');
        $generated = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = Arr::get($row, 'id');

            // A model returning an id we never asked about is a hallucination,
            // not a bonus.
            if (! is_numeric($id) || ! $byId->has((int) $id)) {
                continue;
            }

            $copy = $this->clean($row, $model);

            if ($copy !== null) {
                $generated[(int) $id] = $copy;
            }
        }

        // Anything the model skipped or fumbled still gets copy.
        foreach ($events as $event) {
            if (! isset($generated[$event->id])) {
                $generated[$event->id] = $this->template($event);
            }
        }

        return $generated;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function clean(array $row, string $model): ?GeneratedCopy
    {
        $description = $this->flatten(Arr::get($row, 'description'));
        $meta = $this->flatten(Arr::get($row, 'meta_description'));

        if ($description === '') {
            return null;
        }

        // A link means the model ignored the brief and could point anywhere, so
        // that copy is discarded. Stray markup is only untidy: flatten() has
        // already removed it, and the sentence underneath is usually fine.
        if (Str::contains($description, ['http://', 'https://', ']('])) {
            return null;
        }

        $words = str_word_count($description);
        $max = (int) config('ingest.ai.max_words', 60);

        if ($words > $max * 1.5) {
            return null;
        }

        return new GeneratedCopy(
            description: $description,
            metaDescription: Str::limit($meta !== '' ? $meta : $description, $this->maxMetaLength() - 1, '…'),
            model: $model,
        );
    }

    private function flatten(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, GeneratedCopy>
     */
    private function allTemplates(Collection $events): array
    {
        return $events->mapWithKeys(
            fn (Event $event): array => [$event->id => $this->template($event)],
        )->all();
    }

    /**
     * Spend the good model on what people are about to look at, and the fast
     * one on the long tail.
     *
     * @param  Collection<int, Event>  $events
     */
    private function modelFor(Collection $events): string
    {
        $urgent = $events->contains(
            fn (Event $event): bool => $event->featured
                || ($event->start_datetime?->isBefore(now()->addDays(8)) ?? false),
        );

        return (string) config(
            $urgent ? 'ingest.ai.model_primary' : 'ingest.ai.model_bulk',
        );
    }

    private function consumeBudget(): void
    {
        Cache::add($this->budgetKey(), 0, now()->endOfDay());
        Cache::increment($this->budgetKey());
    }

    private function budgetKey(): string
    {
        return 'ingest:ai-requests:'.now()->toDateString();
    }

    private function batchSize(): int
    {
        return max(1, (int) config('ingest.ai.batch_size', 10));
    }

    private function maxMetaLength(): int
    {
        return (int) config('ingest.ai.max_meta_description', 155);
    }
}
