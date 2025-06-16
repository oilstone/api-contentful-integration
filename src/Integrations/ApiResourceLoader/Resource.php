<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\ApiResourceLoader;

use Api\Guards\OAuth2\Sentinel;
use Api\Repositories\Contracts\Resource as RepositoryContract;
use Api\Schema\Schema as BaseSchema;
use Api\Transformers\Contracts\Transformer as TransformerContract;
use Oilstone\ApiContentfulIntegration\Integrations\Api\Schema\Schema;
use Oilstone\ApiContentfulIntegration\Integrations\Api\Transformers\Transformer;
use Oilstone\ApiContentfulIntegration\Repository;
use Oilstone\ApiResourceLoader\Resources\Resource as BaseResource;

class Resource extends BaseResource
{
    protected ?string $contentType;

    public function repository(?Sentinel $sentinel = null): ?RepositoryContract
    {
        return new Repository($this->contentType, method_exists($this, 'isContextAware') && $this->isContextAware(), $sentinel);
    }

    public function transformer(BaseSchema $schema): ?TransformerContract
    {
        return new Transformer($schema);
    }

    protected function newSchemaObject(): BaseSchema
    {
        return new Schema($this->contentType);
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(?string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }
}
