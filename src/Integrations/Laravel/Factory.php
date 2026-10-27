<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel;

use Contentful\Core\Api\BaseClient;
use Contentful\Delivery\ClientOptions;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    public static function makeDeliveryClient(?string $context = null, bool $withCache = true): Delivery
    {
        if ($context && Config::get('contentful.environments.' . $context . '.delivery.token')) {
            return static::withIntegration(new ContextAwareDelivery(
                Config::get('contentful.environments.' . $context . '.delivery.token'),
                Config::get('contentful.environments.' . $context . '.space'),
                Config::get('contentful.environments.' . $context . '.environment'),
                static::getClientOptions($context, $withCache),
            ));
        }

        return static::withIntegration(new Delivery(
            Config::get('contentful.delivery.token'),
            Config::get('contentful.space'),
            Config::get('contentful.environment'),
            static::getClientOptions(null, $withCache),
        ));
    }

    public static function makePreviewClient(?string $context = null): Preview
    {
        if ($context && Config::get('contentful.environments.' . $context . '.preview.token')) {
            return static::withIntegration(new ContextAwarePreview(
                Config::get('contentful.environments.' . $context . '.preview.token'),
                Config::get('contentful.environments.' . $context . '.space'),
                Config::get('contentful.environments.' . $context . '.environment'),
                static::getClientOptions($context)->withHost('https://preview.contentful.com'),
            ));
        }

        return static::withIntegration(new Preview(
            Config::get('contentful.preview.token'),
            Config::get('contentful.space'),
            Config::get('contentful.environment'),
            static::getClientOptions()->withHost('https://preview.contentful.com'),
        ));
    }

    public static function makeManagementClient(?string $context = null): Management
    {
        $options = array_filter([
            'logger' => static::isDebugEnabled() ? Log::getLogger() : null,
            'guzzle' => static::getHttpClient(),
        ]);

        if ($context && Config::get('contentful.environments.' . $context . '.management.token')) {
            $prefix = 'contentful.environments.' . $context;
            $client = new ContextAwareManagement(Config::get($prefix . '.management.token'), $options);
        } else {
            $prefix = 'contentful';
            $client = new Management(Config::get($prefix . '.management.token'), $options);
        }

        $client->setSpaceId(Config::get($prefix . '.space'));
        $client->setEnvironment(Config::get($prefix . '.environment'));

        return static::withIntegration($client);
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
