<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Api\Bridge;

use Aggregate\Set;
use Api\Pipeline\Pipes\Pipe;
use Oilstone\ApiContentfulIntegration\Query as QueryBuilder;
use Oilstone\ApiContentfulIntegration\Record;
use Psr\Http\Message\ServerRequestInterface;

class QueryResolver
{
    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var Pipe
     */
    protected $pipe;

    /**
     * QueryResolver constructor.
     */
    public function __construct(QueryBuilder $queryBuilder, Pipe $pipe)
    {
        $this->queryBuilder = $queryBuilder;
        $this->pipe = $pipe;
    }

    public function byKey(): ?Record
    {
        return $this->keyedQuery()->first();
    }

    public function record(ServerRequestInterface $request): ?Record
    {
        return $this->resolve($this->keyedQuery($request->getQueryParams()['key'] ?? null), $request)->first();
    }

    public function collection(ServerRequestInterface $request): Set
    {
        return $this->resolve($this->baseQuery(), $request)->get();
    }

    public function resolve(QueryBuilder $queryBuilder, ServerRequestInterface $request): Query
    {
        $parsedQuery = $request->getAttribute('parsedQuery');

        return (new Query($queryBuilder))->include($parsedQuery->getRelations())
            ->select($parsedQuery->getFields())
            ->where($parsedQuery->getFilters())
            ->orderBy($parsedQuery->getSort())
            ->limit($parsedQuery->getLimit())
            ->offset($parsedQuery->getOffset());
    }

    public function keyedQuery(?string $primaryKey = null): QueryBuilder
    {
        if ($primaryKey) {
            $primaryKey = $this->pipe->getResource()->getSchema()->getProperty($primaryKey);
        } else {
            $primaryKey = $this->pipe->getResource()->getSchema()->getPrimary();
        }

        if (! $primaryKey) {
            return $this->baseQuery();
        }

        return $this->baseQuery()->where($primaryKey->alias ?: $primaryKey->getName(), $this->pipe->getKey());
    }

    public function baseQuery(): QueryBuilder
    {
        if ($this->pipe->isScoped()) {
            $scope = $this->pipe->getScope();

            return $this->queryBuilder->where($scope->getKey(), $scope->getValue());
        }

        return $this->queryBuilder;
    }
}
