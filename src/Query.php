<?php

namespace Oilstone\ApiContentfulIntegration;

use Aggregate\Set;
use Api\Exceptions\InvalidQueryArgumentsException;
use Api\Exceptions\UnknownOperatorException;
use Api\Schema\Property;
use Api\Schema\Stitch\Property as StitchProperty;
use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Oilstone\ApiContentfulIntegration\Clients\Preview;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Factory;
use Carbon\Carbon;
use Contentful\Core\Api\DateTimeImmutable;
use Contentful\Core\Api\Location;
use Contentful\Delivery\Query as DeliveryQuery;
use Contentful\Delivery\Resource\Entry;
use Contentful\Delivery\Resource\Tag;
use Illuminate\Support\Str;

class Query
{
    protected ?string $contentType;

    protected Delivery $client;

    protected DeliveryQuery $queryBuilder;

    protected static array $relationMappings = [];

    public function __construct(?string $contentType, Delivery $client)
    {
        $this->contentType = $contentType;
        $this->client = $client;
        $this->queryBuilder = (new DeliveryQuery)->setContentType($contentType);
    }

    public static function make(?string $contentType, Delivery $client): static
    {
        return new static($contentType, $client);
    }

    public function isPreviewMode(): bool
    {
        return $this->client instanceof Preview;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(?string $contentType): static
    {
        $this->contentType = $contentType;
        $this->queryBuilder->setContentType($contentType);

        return $this;
    }

    public function setClient(Delivery $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function with(string $relation): static
    {
        $depth = count(explode('.', $relation)) + 1;

        $this->queryBuilder->setInclude($depth > 10 ? 10 : $depth);

        return $this;
    }

    public function select(array|string $columns): static
    {
        $this->queryBuilder->select(is_array($columns) ? $columns : explode(',', $columns));

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->queryBuilder->orderBy($column, strtoupper($direction) === 'DESC');

        return $this;
    }

    /**
     * @param [string] ...$arguments
     */
    public function where(...$arguments): static
    {
        if (count($arguments) < 2 || count($arguments) > 3) {
            throw new InvalidQueryArgumentsException;
        }

        $field = $arguments[0];

        if ($field instanceof StitchProperty) {
            $field = $field->getAlias();
        }

        if ($field instanceof Property && $field->hasMeta('alias')) {
            $field = $field->alias;
        }

        $value = $arguments[2] ?? $arguments[1];
        $operator = '=';

        if (count($arguments) === 3) {
            $operator = mb_strtolower($arguments[1]);
        }

        switch ($operator) {
            case '=':
            case 'has':
                break;

            case '!=':
            case 'has not':
                $field .= '[ne]';
                break;

            case 'in':
                $field .= '[in]';
                break;

            case 'not in':
                $field .= '[nin]';
                break;

            case '>':
                $field .= '[gt]';
                break;

            case '>=':
                $field .= '[gte]';
                break;

            case '<':
                $field .= '[lt]';
                break;

            case '<=':
                $field .= '[lte]';
                break;

            case 'near':
                $field .= '[near]';
                break;

            case 'contains':
                $field .= '[all]';
                break;

            case 'like':
                $field .= '[match]';
                $value = trim($value, '%');
                break;

            default:
                throw new UnknownOperatorException($operator);
        }

        $this->queryBuilder->where($field, $value);

        return $this;
    }

    /**
     * @param [mixed] $arguments
     */
    public function limit(int $limit): static
    {
        $this->queryBuilder->setLimit($limit);

        return $this;
    }

    /**
     * @param [mixed] $arguments
     */
    public function offset(int $offset): static
    {
        $this->queryBuilder->setSkip($offset);

        return $this;
    }

    public function get(): Set
    {
        return $this->getResultSet();
    }

    public function getResultSet(): Set
    {
        return (new Set)->fill(array_map(function (Entry $item) {
            return (new Record)->fill($this->parseEntry($item));
        }, $this->getEntries()));
    }

    public function getEntries(): array
    {
        return $this->client->getEntries($this->queryBuilder)->getItems();
    }

    public function first(): ?Record
    {
        $result = $this->limit(1)->get();

        return $result->count() ? $result[0] : null;
    }

    public static function setRelationMappings(array $mappings): void
    {
        static::$relationMappings = $mappings;
    }

    public static function resolveRelation(string $relation): ?string
    {
        return static::$relationMappings[$relation] ?? null;
    }

    protected function parseEntry(Entry $entry): array
    {
        $fields = [];

        foreach ($entry->all() as $key => $field) {
            switch (true) {
                case is_array($field) && ($field[0]['sys']['type'] ?? null) === 'ResourceLink':
                    $urns = array_map(fn ($relation) => $relation['sys']['urn'], $field);
                    $client = Factory::makeDeliveryClientFromEntryUrn($urns[0], $this->isPreviewMode());
                    $entryIds = array_map(fn (string $urn) => Str::afterLast($urn, '/'), $urns);
                    $entries = $client->getEntries((new DeliveryQuery)->where('sys.id[in]', $entryIds));

                    // ** Write out items to a new array to keep original order from $entryIds list */
                    $items = array_map(function ($id) use ($entries) {
                        foreach ($entries->getItems() as $item) {
                            if ($item->getId() == $id) {
                                return $item;
                            }
                        }
                    }, $entryIds);

                    $fields[$key] = array_map(function ($data) {
                        if ($data instanceof Entry) {
                            $data = json_decode(json_encode($this->parseEntry($data)), true);
                        }

                        return $data;
                    }, $items);
                    break;

                case is_array($field) && $key === 'nt_experiences':
                    $fields[$key] = array_map(function ($data) {
                        $experience = json_decode(json_encode($data), true);
                        $experience['fields']['nt_audience'] = json_decode(json_encode($data->all()['nt_audience']), true);
                        $experience['fields']['nt_variants'] = json_decode(json_encode($data->all()['nt_variants']), true);

                        return $experience;
                    }, $field);
                    break;

                case is_array($field) && isset($field[0]):
                    $fields[$key] = array_map(function ($data) {
                        if ($data instanceof Entry) {
                            $data = json_decode(json_encode($this->parseEntry($data)), true);
                        }

                        return $data;
                    }, $field);
                    break;

                case is_object($field) && $field instanceof Location:
                    $fields[$key] = [$field->getLatitude(), $field->getLongitude()];
                    break;

                case is_object($field) && $field instanceof DateTimeImmutable:
                    $fields[$key] = Carbon::parse((string) $field)->toDateTimeString();
                    break;

                case is_object($field) && method_exists($field, 'jsonSerialize'):
                    $fields[$key] = $field->jsonSerialize();
                    break;

                default:
                    $fields[$key] = $field;
            }
        }

        return [
            'sys' => [
                'id' => $entry->getId(),
                'contentType' => $entry->getContentType(),
                'publishedAt' => Carbon::parse((string) $entry->getSystemProperties()->getUpdatedAt()),
                'firstPublishedAt' => Carbon::parse((string) $entry->getSystemProperties()->getCreatedAt()),
                'space' => [
                    'sys' => [
                        'id' => $entry->getSystemProperties()->getSpace()->getId(),
                    ],
                ],
            ],
            'fields' => $fields,
            'metadata' => [
                'tags' => array_map(fn (Tag $tag) => $tag->getSystemProperties()->getId(), $entry->getTags()),
                'tagNames' => array_map(fn (Tag $tag) => $tag->getName(), $entry->getTags()),
            ],
        ];
    }
}
