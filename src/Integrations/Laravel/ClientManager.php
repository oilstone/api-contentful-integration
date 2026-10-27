<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel;

use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Oilstone\ApiContentfulIntegration\Clients\Management;
use Oilstone\ApiContentfulIntegration\Clients\Preview;

/**
 * Holds one client per API and context. Bound as a scoped instance so clients are shared for the
 * lifetime of a request or queued job, but request dependent state such as the default locale
 * never leaks between Octane requests or queued jobs.
 */
class ClientManager
{
    /**
     * @var array<string, Delivery>
     */
    protected array $delivery = [];

    /**
     * @var array<string, Preview>
     */
    protected array $preview = [];

    /**
     * @var array<string, Management>
     */
    protected array $management = [];

    public function delivery(?string $context = null): Delivery
    {
        return $this->delivery[$context ?? ''] ??= Factory::makeDeliveryClient($context);
    }

    public function preview(?string $context = null): Preview
    {
        return $this->preview[$context ?? ''] ??= Factory::makePreviewClient($context);
    }

    public function management(?string $context = null): Management
    {
        return $this->management[$context ?? ''] ??= Factory::makeManagementClient($context);
    }

    public function forEntryUrn(string $urn, bool $preview = false): Delivery
    {
        $context = Factory::contextForEntryUrn($urn);

        return $preview ? $this->preview($context) : $this->delivery($context);
    }
}
