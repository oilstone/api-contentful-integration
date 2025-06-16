<?php

namespace Oilstone\ApiContentfulIntegration\Concerns;

trait ContextAware
{
    protected bool $contextAware = true;

    public function isContextAware(): bool
    {
        return $this->contextAware;
    }
}
