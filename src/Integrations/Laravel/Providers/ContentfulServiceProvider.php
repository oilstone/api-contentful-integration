<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel\Providers;

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
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\ClientManager;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Factory;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Integrations\SystemStatus\ContentfulMonitor;
use Oilstone\SystemStatus\SystemStatus;

class ContentfulServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * Client lifetimes are owned by the scoped ClientManager, so the client bindings below are
     * plain binds that delegate to it rather than singletons that would outlive the request.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'contentful');

        $this->app->scoped(ClientManager::class);

        $this->app->bind(Delivery::class, function (Application $app): Delivery {
            return Factory::shouldUsePreview()
                ? $app->make(Preview::class)
                : $app->make(ClientManager::class)->delivery();
        });

        $this->app->bind(Preview::class, function (Application $app): Preview {
            return $app->make(ClientManager::class)->preview();
        });

        $this->app->bind(Management::class, function (Application $app): Management {
            return $app->make(ClientManager::class)->management();
        });

        $this->registerContextAwareClients();
        $this->registerSystemStatusMonitor();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('contentful.php'),
            ], 'contentful-config');
        }

        $this->registerCacheStore();
    }

    protected function configPath(): string
    {
        return __DIR__.'/../config/contentful.php';
    }

    /**
     * Context aware clients are opt-in. When disabled they resolve to the standard clients so
     * resources and transformers that request them still work against the default space.
     */
    protected function registerContextAwareClients(): void
    {
        if (! Factory::isContextAware()) {
            $this->app->alias(Delivery::class, ContextAwareDelivery::class);
            $this->app->alias(Preview::class, ContextAwarePreview::class);
            $this->app->alias(Management::class, ContextAwareManagement::class);

            return;
        }

        $this->app->bind(ContextAwareDelivery::class, function (Application $app): Delivery {
            return Factory::shouldUsePreview()
                ? $app->make(ContextAwarePreview::class)
                : $app->make(ClientManager::class)->delivery((new Context($app))->current());
        });

        $this->app->bind(ContextAwarePreview::class, function (Application $app): Preview {
            return $app->make(ClientManager::class)->preview((new Context($app))->current());
        });

        $this->app->bind(ContextAwareManagement::class, function (Application $app): Management {
            return $app->make(ClientManager::class)->management((new Context($app))->current());
        });
    }

    /**
     * oilstone/system-status is optional. Extending the shared instance makes the monitor
     * available to the status checks and to watches alike.
     */
    protected function registerSystemStatusMonitor(): void
    {
        if (! class_exists(SystemStatus::class)) {
            return;
        }

        $this->callAfterResolving(SystemStatus::class, function (SystemStatus $status): void {
            $status->extend('contentful', ContentfulMonitor::class);
        });
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
}
