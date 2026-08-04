<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel\Cache;

use Contentful\Delivery\Client;
use Contentful\Delivery\ResourcePool\Standard;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Factory;

/**
 * Contentful offers no granular cache control, so invalidation is handled here instead.
 *
 * Resource keys are deterministic and can be deleted individually without contacting the API.
 * Query keys are hashes of the query object and cannot be enumerated, so the query pool is
 * namespaced by version and invalidated by incrementing it. Superseded entries are left to
 * expire via the pool TTL rather than being deleted.
 */
class Invalidator
{
    public const RESOURCE_VERSION_KEY = 'contentful.cache.version.resources';

    public const QUERY_VERSION_KEY = 'contentful.cache.version.queries';

    public static function store(): CacheRepository
    {
        return Cache::store(Config::get('contentful.cache.store') ?: Config::get('cache.default'));
    }

    public static function resourceVersion(): int
    {
        return static::version(static::RESOURCE_VERSION_KEY);
    }

    public static function queryVersion(): int
    {
        return static::version(static::QUERY_VERSION_KEY);
    }

    /**
     * Removes a single entry or asset from the resource pool across every space, environment
     * and locale the application is configured to read from.
     */
    public static function forgetResource(string $type, string $id): bool
    {
        $keys = [];

        foreach (static::scopes() as [$space, $environment]) {
            $resourcePool = new Standard(Client::API_DELIVERY, $space, $environment);

            foreach (static::locales() as $locale) {
                $keys[] = $resourcePool->generateKey($type, $id, ['locale' => $locale]);
            }
        }

        return $keys === [] || Factory::makeContentfulCachePool()->deleteItems($keys);
    }

    public static function flushResources(): int
    {
        return static::increment(static::RESOURCE_VERSION_KEY);
    }

    public static function flushQueries(): int
    {
        return static::increment(static::QUERY_VERSION_KEY);
    }

    public static function flush(): void
    {
        static::flushResources();
        static::flushQueries();
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function scopes(): array
    {
        $scopes = [[
            (string) Config::get('contentful.space'),
            (string) Config::get('contentful.environment'),
        ]];

        foreach ((array) Config::get('contentful.environments') as $environment) {
            if (! empty($environment['space'])) {
                $scopes[] = [
                    (string) $environment['space'],
                    (string) ($environment['environment'] ?? Config::get('contentful.environment')),
                ];
            }
        }

        return array_values(array_unique($scopes, SORT_REGULAR));
    }

    /**
     * @return array<int, string>
     */
    protected static function locales(): array
    {
        $locales = (array) (Config::get('contentful.cache.locales') ?: []);

        if ($locales === []) {
            $locales[] = Config::get('contentful.defaultLocale');

            foreach ((array) Config::get('contentful.environments') as $environment) {
                if (! empty($environment['defaultLocale'])) {
                    $locales[] = $environment['defaultLocale'];
                }
            }
        }

        $locales[] = '*';

        return array_values(array_unique(array_filter($locales)));
    }

    protected static function version(string $key): int
    {
        $store = static::store();
        $version = $store->get($key);

        if ($version === null) {
            $store->forever($key, $version = 1);
        }

        return (int) $version;
    }

    protected static function increment(string $key): int
    {
        $store = static::store();

        static::version($key);

        return (int) $store->increment($key);
    }
}
