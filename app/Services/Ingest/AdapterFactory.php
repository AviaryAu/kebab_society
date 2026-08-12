<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Models\IngestSource;
use App\Services\Ingest\Contracts\SourceAdapter;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Resolves a source's adapter key to an instance.
 *
 * The registry is an allow-list in config rather than a class name in the
 * database. A source row is administrator input, and a string from a form
 * should never be able to name a class the container will then instantiate.
 */
class AdapterFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(IngestSource $source): SourceAdapter
    {
        return $this->makeFromKey($source->adapter);
    }

    public function makeFromKey(string $key): SourceAdapter
    {
        $registry = $this->registry();

        if (! array_key_exists($key, $registry)) {
            throw new RuntimeException("Unknown ingest adapter [{$key}].");
        }

        $adapter = $this->container->make($registry[$key]);

        if (! $adapter instanceof SourceAdapter) {
            throw new RuntimeException("Adapter [{$key}] does not implement SourceAdapter.");
        }

        return $adapter;
    }

    public function supports(string $key): bool
    {
        return array_key_exists($key, $this->registry());
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->registry());
    }

    /**
     * @return array<string, class-string>
     */
    private function registry(): array
    {
        /** @var array<string, class-string> $registry */
        $registry = config('ingest.adapters', []);

        return $registry;
    }
}
