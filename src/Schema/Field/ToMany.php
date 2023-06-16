<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;

class ToMany extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type($name);
    }

    public function serializeValue($value, Context $context): mixed
    {
        $meta = $this->serializeMeta($context);

        if ((($context->include === null && !$this->linkage) || !$value) && !$meta) {
            return null;
        }

        $relationship = [];

        if ($value) {
            $relationship['data'] = array_map(
                fn($model) => $context->serializer->addIncluded($this, $model, $context->include),
                $value,
            );
        }

        if ($meta) {
            $relationship['meta'] = $meta;
        }

        return $relationship;
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if (!is_array($value) || !array_key_exists('data', $value)) {
            throw new BadRequestException('relationship does not include data key');
        }

        if (!array_is_list($value['data'])) {
            throw new BadRequestException('relationship data must be a list of identifier objects');
        }

        $models = array_map(
            fn($identifier) => $this->findResourceForIdentifier($identifier, $context),
            $value['data'],
        );

        if ($this->deserializer) {
            return ($this->deserializer)($models, $context);
        }

        return $models;
    }
}
