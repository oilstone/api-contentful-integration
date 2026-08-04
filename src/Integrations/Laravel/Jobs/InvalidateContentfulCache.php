<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Cache\Invalidator;
use Throwable;

/**
 * Given an entity, only that entity is dropped from the resource pool. Without one, the whole
 * resource pool is invalidated. The query pool is invalidated either way since query results
 * cannot be mapped back to the entities they contain.
 */
class InvalidateContentfulCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        protected ?string $type = null,
        protected ?string $id = null,
    ) {}

    public function handle(): void
    {
        try {
            if ($this->type && $this->id) {
                Invalidator::forgetResource($this->type, $this->id);
            } else {
                Invalidator::flushResources();
            }

            Invalidator::flushQueries();
        } catch (Throwable $exception) {
            Log::warning('WEBHOOK - Contentful - Failed to invalidate Contentful cache', [
                'type' => $this->type,
                'id' => $this->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('WEBHOOK - Contentful - Cache invalidation job failed', [
            'type' => $this->type,
            'id' => $this->id,
            'message' => $exception?->getMessage(),
        ]);
    }
}
