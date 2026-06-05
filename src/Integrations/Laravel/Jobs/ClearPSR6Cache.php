<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel\Jobs;

use Contentful\Delivery\Cache\CacheClearer;
use Contentful\Delivery\ResourcePool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Factory;
use Throwable;

class ClearPSR6Cache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * This is an aggressive cache clearance routine since Contentful does not offer granular cache control.
     */
    public function handle(): void
    {
        Redis::throttle('psr6-cache-clear')
            ->allow(1)
            ->every(60)
            ->block(0)
            ->then(function (): void {
                try {
                    // Use a non-cached client and the same PSR-6 pool that Contentful uses.
                    $client = Factory::makeDeliveryClient(null, false);
                    $psr6CachePool = Factory::makeContentfulCachePool();

                    $resourcePool = new ResourcePool(
                        $client,
                        $psr6CachePool,
                        true,
                        true,
                    );

                    $clearer = new CacheClearer(
                        $client,
                        $resourcePool,
                        $psr6CachePool,
                    );

                    // Clear structure and content cache.
                    $clearer->clear(true);
                } catch (Throwable $exception) {
                    if (config('app.debug')) {
                        Log::warning('WEBHOOK - Contentful - Failed to clear Contentful cache', [
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            }, function (): void {
                // Lock not acquired within 0 seconds, just drop this job.
                $this->delete();
            });
    }
}
