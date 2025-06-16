<?php

namespace Oilstone\ApiContentfulIntegration;

use Illuminate\Foundation\Application;

class Context
{
    public function __construct(
        protected ?Application $app = null
    ) {}

    public function switch(?string $context, callable $callback): void
    {
        $previousContext = $this->current();

        $this->app()->instance('contentfulContext', $context);

        $callback();

        if (! $previousContext) {
            $this->app()->offsetUnset('contentfulContext');

            return;
        }

        $this->app()->instance('contentfulContext', $previousContext);
    }

    public function current(): ?string
    {
        if (! $this->app()->bound('contentfulContext')) {
            return null;
        }

        return $this->app()->contentfulContext;
    }

    public function app(): Application
    {
        return $this->app ?? app();
    }
}
