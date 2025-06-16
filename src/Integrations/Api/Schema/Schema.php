<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Api\Schema;

use Api\Schema\Schema as BaseSchema;

class Schema extends BaseSchema
{
    protected ?string $contentType;

    /**
     * @return void
     */
    public function __construct(?string $contentType = null)
    {
        parent::__construct();

        $this->contentType = $contentType;
    }

    public function contentType(?string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}
