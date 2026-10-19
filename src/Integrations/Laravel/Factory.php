<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel;

use Contentful\Delivery\Client as DeliveryClient;
use Contentful\Delivery\ClientOptions;
use Contentful\Management\Client as ManagementClient;
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
    public static function makeDeliveryClient(?string $context = null, bool $withCache = true): DeliveryClient
    {
        if ($context && Config::get('contentful.environments.' . $context . '.delivery.token')) {
            return new ContextAwareDelivery(
                Config::get('contentful.environments.' . $context . '.delivery.token'),
                Config::get('contentful.environments.' . $context . '.space'),
                Config::get('contentful.environments.' . $context . '.environment'),
                static::getClientOptions($context, $withCache),
            );
        }

        return new Delivery(
            Config::get('contentful.delivery.token'),
            Config::get('contentful.space'),
            Config::get('contentful.environment'),
            static::getClientOptions(null, $withCache),
        );
    }

    public static function makePreviewClient(?string $context = null): Preview
    {
        $clientOptions = static::getClientOptions()->withHost('https://preview.contentful.com');

        if ($context && Config::get('contentful.environments.' . $context . '.preview.token')) {
            return new ContextAwarePreview(
                Config::get('contentful.environments.' . $context . '.preview.token'),
                Config::get('contentful.environments.' . $context . '.space'),
                Config::get('contentful.environments.' . $context . '.environment'),
                $clientOptions,
            );
        }

        return new Preview(
            Config::get('contentful.preview.token'),
            Config::get('contentful.space'),
            Config::get('contentful.environment'),
            $clientOptions,
        );
    }

    public static function makeManagementClient(?string $context = null): ManagementClient
    {
        $client = null;

        if ($context && Config::get('contentful.environments.' . $context . '.management.token')) {
            $client = new ContextAwareManagement(
                Config::get('contentful.environments.' . $context . '.management.token'),
                array_filter([
                    'logger' => static::isDebugEnabled() ? Log::getLogger() : null,
                    'guzzle' => static::getHttpClient(),
                ]),
            );
        }

        if (! $client) {
            $client = new Management(
                Config::get('contentful.management.token'),
                array_filter([
                    'logger' => static::isDebugEnabled() ? Log::getLogger() : null,
                    'guzzle' => static::getHttpClient(),
                ]),
            );
        }

        $client->setSpaceId(Config::get('contentful.space'));
        $client->setEnvironment(Config::get('contentful.environment'));

        return $client;
    }

    public static function makeDeliveryClientFromEntryUrn(string $urn, bool $preview = false): Delivery
    {
        $spaceId = Str::before(Str::after($urn, 'spaces/'), '/');

        foreach (Config::get('contentful.environments') as $context => $environment) {
            if ($environment['space'] === $spaceId) {
                return $preview ? static::makePreviewClient($context) : static::makeDeliveryClient($context);
            }
        }

        return $preview ? static::makePreviewClient() : static::makeDeliveryClient();
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
