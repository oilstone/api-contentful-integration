<?php

namespace Oilstone\ApiContentfulIntegration;

use Api\Pipeline\Pipes\Pipe;
use Api\Repositories\Contracts\Resource as RepositoryInterface;
use Api\Result\Contracts\Collection as ResultCollectionInterface;
use Api\Result\Contracts\Record as ResultRecordInterface;
use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Contentful\Delivery\Resource\Tag as TagEntity;
use Illuminate\Support\Facades\Cache;
use Oilstone\ApiContentfulIntegration\Exceptions\MethodNotAllowedException;
use Psr\Http\Message\ServerRequestInterface;

class Tag implements RepositoryInterface
{
    protected Delivery $client;

    /**
     * @return void
     */
    public function __construct(Delivery $client)
    {
        $this->client = $client;
    }

    public function getAllTags(): array
    {
        return array_map(fn (array $tag) => Record::make($tag), Cache::remember('contentful:tags:' . $this->client->getSpaceId(), 1800, function () {
            return array_map(fn (TagEntity $tag) => $tag->jsonSerialize(), $this->client->getAllTags());
        }));
    }

    public function getTagById(string $id): ?ResultRecordInterface
    {
        $tag = $this->client->getTag($id);

        if (! $tag) {
            return null;
        }

        return (new Record)->fill($tag->jsonSerialize());
    }

    public function getByKey(Pipe $pipe): ?ResultRecordInterface
    {
        return $this->getTagById($pipe->getKey());
    }

    public function getCollection(Pipe $pipe, ServerRequestInterface $request): ResultCollectionInterface
    {
        return Collection::make($this->getAllTags());
    }

    public function getRecord(Pipe $pipe, ServerRequestInterface $request): ?ResultRecordInterface
    {
        return $this->getByKey($pipe);
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
}
