<?php

namespace Oilstone\ApiContentfulIntegration;

use Aggregate\Map;
use Api\Result\Contracts\Record as ApiRecordContract;
use Contentful\Delivery\SystemProperties\Entry;

class Record extends Map implements ApiRecordContract
{
    public static function make(array $item): static
    {
        return (new static)->fill($item);
    }

    public function getRelations(): array
    {
        $relations = [];

        foreach ($this->extractRelations() as $relation => $data) {
            $relations[Query::resolveRelation($relation) ?? $relation] = $data;
        }

        return $relations;
    }

    public function getAttributes(): array
    {
        return $this->all();
    }

    public function getAttribute(string $key): mixed
    {
        return $this->get($key);
    }

    protected function extractRelations(): array
    {
        return array_filter(array_map(function (mixed $property) {
            if (! is_array($property)) {
                return null;
            }

            if (isset($property['sys']) && $property['sys'] instanceof Entry) {
                return (new static)->fill(json_decode(json_encode($property), true));
            }

            if (isset($property['sys']['id'])) {
                return (new static)->fill($property);
            }

            if (isset($property[0]['sys']['id'])) {
                return Collection::make($property);
            }

            return null;
        }, $this->all()['fields'] ?? []));
    }
}
