<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel;

use Contentful\Core\Api\IntegrationInterface;

class Integration implements IntegrationInterface
{
    public function getIntegrationName(): string
    {
        return 'contentful.laravel'; // This name is used to determine which version of the API should be used (why wouldn't you use a config for this??)
    }

    public function getIntegrationPackageName(): string
    {
        return 'contentful/laravel'; // This name is used to determine which version of the API should be used (why wouldn't you use a config for this??)
    }
}
