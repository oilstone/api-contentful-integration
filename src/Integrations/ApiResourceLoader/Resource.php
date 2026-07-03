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

    /**
     * @var array<int, callable>
     */
    protected array $beforeTransformCallbacks = [];

    /**
     * @var array<int, callable>
     */
    protected array $afterTransformCallbacks = [];

    public function repository(?Sentinel $sentinel = null): ?RepositoryContract
    {
        return new Repository($this->contentType, method_exists($this, 'isContextAware') && $this->isContextAware(), $sentinel);
    }

    public function transformer(BaseSchema $schema): ?TransformerContract
    {
        return new Transformer($schema);
    }

    public function beforeTransform(callable $callback): static
    {
        $this->beforeTransformCallbacks[] = $callback;

        return $this;
    }

    public function afterTransform(callable $callback): static
    {
        $this->afterTransformCallbacks[] = $callback;

        return $this;
    }

    public function makeTransformer(BaseSchema $schema): ?TransformerContract
    {
        if (isset($this->cached['transformer'])) {
            return $this->cached['transformer'];
        }

        $transformer = parent::makeTransformer($schema);

        if (! $transformer) {
            return null;
        }

        foreach ($this->beforeTransformCallbacks as $callback) {
            if (method_exists($transformer, 'before')) {
                $transformer->before($callback);
            }
        }

        foreach ($this->afterTransformCallbacks as $callback) {
            if (method_exists($transformer, 'after')) {
                $transformer->after($callback);
            }
        }

        return $this->cached['transformer'] = $transformer;
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
