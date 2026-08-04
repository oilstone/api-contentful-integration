<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel\Providers;

use Contentful\Core\Api\IntegrationInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Delivery as ContextAwareDelivery;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Management as ContextAwareManagement;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Preview as ContextAwarePreview;
use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Oilstone\ApiContentfulIntegration\Clients\Management;
use Oilstone\ApiContentfulIntegration\Clients\Preview;
use Oilstone\ApiContentfulIntegration\Context;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Factory;

class ContentfulServiceProvider extends ServiceProvider implements IntegrationInterface
{
    /**
     * Register any other events for your application.
     */
    public function boot(): void
    {
        $configFile = (string) \realpath(__DIR__.'/../config/contentful.php');

        $this->publishes([
            $configFile => $this->app->make('path.config').'/contentful.php',
        ]);

        $this->mergeConfigFrom($configFile, 'contentful');

        $this->registerCacheStore();
    }

    /**
     * The cache pool is an implementation detail of this package, so the store is defined here
     * rather than requiring the application to publish and maintain a cache config. An existing
     * store of the same name always wins.
     */
    protected function registerCacheStore(): void
    {
        $store = Config::get('contentful.cache.store');

        if (! $store || Config::get('cache.stores.'.$store)) {
            return;
        }

        Config::set('cache.stores.'.$store, array_filter([
            'driver' => Config::get('contentful.cache.driver') ?: 'redis',
            'connection' => Config::get('contentful.cache.connection'),
            'lock_connection' => Config::get('contentful.cache.lockConnection'),
        ]));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(Delivery::class, function (Application $app): Delivery {
            if (Config::get('artfund.preview') || ($this->app->request?->method() && $this->app->request->method() !== 'GET')) {
                return $app->make(Preview::class);
            }

            $client = Factory::makeDeliveryClient();
            $client->useIntegration($this);

            return $client;
        });

        $this->app->singleton(Preview::class, function (): Preview {
            $client = Factory::makePreviewClient();
            $client->useIntegration($this);

            return $client;
        });

        $this->app->singleton(Management::class, function (): Management {
            $client = Factory::makeManagementClient();
            $client->useIntegration($this);

            return $client;
        });

        $this->app->bind(ContextAwareDelivery::class, function (Application $app): Delivery {
            if (Config::get('artfund.preview') || ($this->app->request?->method() && $this->app->request->method() !== 'GET')) {
                return $app->make(ContextAwarePreview::class);
            }

            $client = Factory::makeDeliveryClient((new Context($app))->current());
            $client->useIntegration($this);

            return $client;
        });

        $this->app->bind(ContextAwarePreview::class, function (Application $app): Preview {
            $client = Factory::makePreviewClient((new Context($app))->current());
            $client->useIntegration($this);

            return $client;
        });

        $this->app->bind(ContextAwareManagement::class, function (Application $app): Management {
            $client = Factory::makeManagementClient((new Context($app))->current());
            $client->useIntegration($this);

            return $client;
        });
    }

    public function getIntegrationName(): string
    {
        return 'contentful.laravel'; // This name is used to determine which version of the API should be used (why wouldn't you use a config for this??)
    }

    public function getIntegrationPackageName(): string
    {
        return 'contentful/laravel'; // This name is used to determine which version of the API should be used (why wouldn't you use a config for this??)
    }
}
