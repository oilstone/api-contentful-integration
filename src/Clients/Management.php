<?php

namespace Oilstone\ApiContentfulIntegration\Clients;

use Contentful\Management\Client;
use Contentful\Management\Proxy\EnvironmentProxy;

class Management extends Client
{
    protected string $spaceId = '';

    protected string $environment = '';

    public function setSpaceId(string $spaceId): static
    {
        $this->spaceId = $spaceId;

        return $this;
    }

    public function setEnvironment(string $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    public function resolveEnvironmentProxy(): EnvironmentProxy
    {
        return $this->getEnvironmentProxy($this->spaceId, $this->environment);
    }
}
