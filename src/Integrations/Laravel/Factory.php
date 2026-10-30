<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel;

use Contentful\Core\Api\BaseClient;
use Contentful\Delivery\ClientOptions;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Delivery as ContextAwareDelivery;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Management as ContextAwareManagement;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Preview as ContextAwarePreview;
use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Oilstone\ApiContentfulIntegration\Clients\Management;
use Oilstone\ApiContentfulIntegration\Clients\Preview;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Cache\Invalidator;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

class Factory
{
    public const APIS = ['delivery', 'preview', 'management'];

    public static function makeDeliveryClient(?string $context = null, bool $withCache = true): Delivery
    {
        $context = static::resolveContext('delivery', $context);
        $credentials = static::credentials('delivery', $context);
        $class = $context ? ContextAwareDelivery::class : Delivery::class;

        return static::withIntegration(new $class(
            $credentials['token'],
            $credentials['space'],
            $credentials['environment'],
            static::getClientOptions($context, $withCache),
        ));
    }

    public static function makePreviewClient(?string $context = null): Preview
    {
        $context = static::resolveContext('preview', $context);
        $credentials = static::credentials('preview', $context);
        $class = $context ? ContextAwarePreview::class : Preview::class;

        return static::withIntegration(new $class(
            $credentials['token'],
            $credentials['space'],
            $credentials['environment'],
            static::getClientOptions($context)->withHost('https://preview.contentful.com'),
        ));
    }

    public static function makeManagementClient(?string $context = null): Management
    {
        $options = array_filter([
            'logger' => static::isDebugEnabled() ? Log::getLogger() : null,
            'guzzle' => static::getHttpClient(),
        ]);

        $context = static::resolveContext('management', $context);
        $credentials = static::credentials('management', $context);
        $class = $context ? ContextAwareManagement::class : Management::class;

        $client = new $class($credentials['token'], $options);
        $client->setSpaceId($credentials['space']);
        $client->setEnvironment($credentials['environment']);

        return static::withIntegration($client);
    }

    /**
     * The token, space and environment to use for an API. A context's settings are only used
     * when that context has a token for the API, otherwise the default space's are.
     *
     * @param  value-of<self::APIS>  $api
     * @return array{token: ?string, space: ?string, environment: ?string}
     */
    public static function credentials(string $api, ?string $context = null): array
    {
        $context = static::resolveContext($api, $context);
        $prefix = $context ? 'contentful.environments.' . $context : 'contentful';

        return [
            'token' => Config::get($prefix . '.' . $api . '.token'),
            'space' => Config::get($prefix . '.space'),
            'environment' => Config::get($prefix . '.environment'),
        ];
    }

    /**
     * The context when it has a token for the API, or null to fall back to the default space.
     */
    protected static function resolveContext(string $api, ?string $context): ?string
    {
        if (! in_array($api, static::APIS, true)) {
            throw new InvalidArgumentException('Unknown Contentful API "' . $api . '"');
        }

        return $context && Config::get('contentful.environments.' . $context . '.' . $api . '.token') ? $context : null;
    }

    public static function makeDeliveryClientFromEntryUrn(string $urn, bool $preview = false): Delivery
    {
        $context = static::contextForEntryUrn($urn);

        return $preview ? static::makePreviewClient($context) : static::makeDeliveryClient($context);
    }

    /**
     * The configured context whose space matches the entry URN, or null for the default space.
     */
    public static function contextForEntryUrn(string $urn): ?string
    {
        if (! static::isContextAware()) {
            return null;
        }

        $spaceId = Str::before(Str::after($urn, 'spaces/'), '/');

        foreach ((array) Config::get('contentful.environments') as $context => $environment) {
            if (($environment['space'] ?? null) === $spaceId) {
                return (string) $context;
            }
        }

        return null;
    }

    /**
     * Content is read from the Preview API when forced by config, or for any non-GET request.
     */
    public static function shouldUsePreview(): bool
    {
        if (Config::get('contentful.preview.enabled')) {
            return true;
        }

        return app()->bound('request') && app('request')->method() !== 'GET';
    }

    /**
     * @template T of BaseClient
     *
     * @param  T  $client
     * @return T
     */
    protected static function withIntegration(BaseClient $client): BaseClient
    {
        $client->useIntegration(new Integration);

        return $client;
    }

    /**
     * Resource entries are only ever written when the key is absent, so without a TTL a stale
     * entry would survive until explicitly deleted.
     */
    public static function makeContentfulCachePool(): CacheItemPoolInterface
    {
        return new Psr16Adapter(
            Invalidator::store(),
            'contentful.r' . Invalidator::resourceVersion(),
            (int) (Config::get('contentful.cache.ttl') ?? 86400),
        );
    }

    public static function makeContentfulQueryCachePool(): CacheItemPoolInterface
    {
        return new Psr16Adapter(
            Invalidator::store(),
            'contentful.q' . Invalidator::queryVersion(),
            static::getQueryCacheLifetime(),
        );
    }

    protected static function getQueryCacheLifetime(): int
    {
        return (int) (Config::get('contentful.cache.queryTtl') ?? 300);
    }

    protected static function getClientOptions(?string $context = null, bool $withCache = false): ClientOptions
    {
        $options = (new ClientOptions())->withHttpClient(static::getHttpClient());

        if ($withCache && !app()->runningInConsole()) {
            $options
                ->withQueryCache(static::makeContentfulQueryCachePool(), static::getQueryCacheLifetime())
                ->withCache(static::makeContentfulCachePool(), true, true);
        }

        if ($locale = request()->get('locale') ?: ($context ? Config::get('contentful.environments.' . $context . '.defaultLocale') : Config::get('contentful.defaultLocale'))) {
            $options->withDefaultLocale($locale);
        }

        if (static::isDebugEnabled()) {
            $options->withLogger(Log::getLogger());
        }

        return $options;
    }

    public static function isContextAware(): bool
    {
        return (bool) (Config::get('contentful.contextAware') ?? false);
    }

    protected static function isDebugEnabled(): bool
    {
        return (bool) (Config::get('contentful.debug') ?? false);
    }

    protected static function getHttpClient(): Client
    {
        return new Client([
            'verify' => Config::get('app.env') !== 'local',
        ]);
    }
}
