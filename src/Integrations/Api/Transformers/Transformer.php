<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Api\Transformers;

use Api\Result\Contracts\Record;
use Api\Schema\Schema;
use Api\Transformers\Contracts\Transformer as Contract;
use Oilstone\ApiContentfulIntegration\Clients\ContextAware\Delivery;
use Oilstone\ApiContentfulIntegration\Integrations\Api\Schema\Schema as ContentfulSchema;
use Carbon\Carbon;
use Contentful\Delivery\Resource\ContentType;
use Contentful\Delivery\SystemProperties\Entry;
use Contentful\RichText\Parser;
use Contentful\RichText\Renderer;
use stdClass;

class Transformer implements Contract
{
    protected Schema $schema;

    /**
     * @var Parser
     */
    protected $richtextParser;

    /**
     * @var Renderer
     */
    protected $richtextRenderer;

    /**
     * @var array<int, callable>
     */
    protected array $beforeCallbacks = [];

    /**
     * @var array<int, callable>
     */
    protected array $afterCallbacks = [];

    public function __construct(ContentfulSchema $schema)
    {
        $this->schema = $schema;
        $this->richtextParser = app(Delivery::class)->getRichTextParser();
        $this->richtextRenderer = new Renderer;
    }

    public function before(callable $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function transform(Record $record): array
    {
        $attributes = $record->getAttributes();

        foreach ($this->beforeCallbacks as $callback) {
            $attributes = $callback($attributes, $record, $this->schema);
        }

        $transformed = $this->transformSchema($this->schema, $attributes);

        foreach ($this->afterCallbacks as $callback) {
            $transformed = $callback($transformed, $attributes, $record, $this->schema);
        }

        return $transformed;
    }

    public function reverse(array $attributes): array
    {
        return $attributes;
    }

    public function transformMetaData(Record $record): array
    {
        return [];
    }

    protected function transformSchema(Schema $schema, array $attributes): array
    {
        $transformed = [];

        foreach ($schema->getProperties() as $property) {
            if ($property->hasMeta('validationOnly')) {
                continue;
            }

            if ($property->getAccepts() instanceof Schema && $property->getType() !== 'collection') {
                $transformed[$property->getName()] = $this->transformSchema($property->getAccepts(), $attributes);

                continue;
            }

            $key = $property->alias ?: $property->getName();
            $path = explode('.', $key);
            $currentAttributes = $attributes;

            while (count($path) > 1) {
                $currentAttributes = $currentAttributes[array_shift($path)] ?? [];

                if ($currentAttributes instanceof ContentType) {
                    $currentAttributes = json_decode(json_encode($currentAttributes), true);
                }

                if ($currentAttributes instanceof Entry) {
                    $currentAttributes = json_decode(json_encode($currentAttributes), true);
                }

                if ($currentAttributes instanceof stdClass) {
                    $currentAttributes = json_decode(json_encode($currentAttributes), true);
                }
            }

            if (is_object($currentAttributes) && method_exists($currentAttributes, 'jsonSerialize')) {
                $currentAttributes = $currentAttributes->jsonSerialize();
            }

            $value = $currentAttributes[$path[0]] ?? null;

            if ($property->hasMeta('beforeTransform') && is_callable($property->beforeTransform)) {
                $value = ($property->beforeTransform)($value, $attributes);
            }

            if ($value) {
                switch ($property->getType()) {
                    case 'richtext':
                        $value = $this->transformRichText($value);
                        break;

                    case 'date':
                        $value = Carbon::parse($value)->toDateString();
                        break;

                    case 'datetime':
                    case 'timestamp':
                        $value = Carbon::parse($value)->toDateTimeString();
                        break;

                    case 'collection':
                        $value = array_values(array_filter(array_map(function ($item) use ($property) {
                            return $item ? $this->transformSchema($property->getAccepts(), $item) : null;
                        }, $value)));
                        break;
                }
            }

            if ($property->hasMeta('afterTransform') && is_callable($property->afterTransform)) {
                $value = ($property->afterTransform)($value, $attributes);
            }

            $transformed[$property->getName()] = $value;
        }

        return $transformed;
    }

    protected function transformRichText(array $value): string
    {
        $value = json_decode(json_encode($value), true);

        return nl2br($this->richtextRenderer->render($this->richtextParser->parse($value)));
    }
}
