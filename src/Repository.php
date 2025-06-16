<?php

namespace Oilstone\ApiContentfulIntegration;

use Api\Guards\Contracts\Sentinel;
use Api\Pipeline\Pipes\Pipe;
use Api\Repositories\Contracts\Resource as RepositoryInterface;
use Api\Result\Contracts\Collection as ResultCollectionInterface;
use Api\Result\Contracts\Record as ResultRecordInterface;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Delivery as ContextAwareDelivery;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Preview;
use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Oilstone\ApiContentfulIntegration\Integrations\Api\Bridge\QueryResolver;
use Illuminate\Support\Facades\App;
use Psr\Http\Message\ServerRequestInterface;
use Oilstone\ApiContentfulIntegration\Exceptions\MethodNotAllowedException;

class Repository implements RepositoryInterface
{
    protected ?string $contentType;

    protected bool $contextAware;

    public function __construct(?string $contentType = null, bool $contextAware = false, ?Sentinel $sentinel = null)
    {
        if (property_exists($this, 'sentinel')) {
            $this->sentinel = $sentinel;
        }

        $this->contentType = $contentType;
        $this->contextAware = $contextAware;
    }

    public function getByKey(Pipe $pipe): ?ResultRecordInterface
    {
        return (new QueryResolver(
            new Query($this->contentType, $this->getClient()),
            $pipe
        ))->byKey();
    }

    public function getCollection(Pipe $pipe, ServerRequestInterface $request): ResultCollectionInterface
    {
        return Collection::make(
            (new QueryResolver(
                new Query($request->getQueryParams()['contentType'] ?? $this->contentType, $this->getClient($request)),
                $pipe
            ))->collection($request)->all()
        );
    }

    public function getRecord(Pipe $pipe, ServerRequestInterface $request): ?ResultRecordInterface
    {
        return (new QueryResolver(
            new Query($request->getQueryParams()['contentType'] ?? $this->contentType, $this->getClient($request)),
            $pipe
        ))->record($request);
    }

    public function create(Pipe $pipe, ServerRequestInterface $request): ResultRecordInterface
    {
        throw new MethodNotAllowedException;
    }

    public function update(Pipe $pipe, ServerRequestInterface $request): ResultRecordInterface
    {
        throw new MethodNotAllowedException;
    }

    public function delete(Pipe $pipe): ResultRecordInterface
    {
        throw new MethodNotAllowedException;
    }

    protected function getClient(?ServerRequestInterface $request = null): Delivery
    {
        if ($request && ($request->getQueryParams()['preview'] ?? false) && ($request->getQueryParams()['previewId'] ?? false)) {
            return App::make(Preview::class);
        }

        return $this->contextAware ? app(ContextAwareDelivery::class) : app(Delivery::class);
    }
}
