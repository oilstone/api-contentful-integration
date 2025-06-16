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

    public function __construct(ContentfulSchema $schema)
    {
        $this->schema = $schema;
        $this->richtextParser = app(Delivery::class)->getRichTextParser();
        $this->richtextRenderer = new Renderer;
    }

    public function transform(Record $record): array
    {
        return $this->transformSchema($this->schema, $record->getAttributes());
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
