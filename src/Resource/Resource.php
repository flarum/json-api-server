<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\Field;

/**
 * @template M of object
 * @template C of Context
 */
interface Resource
{
    /**
     * Get the resource type.
     */
    public function type(): string;

    /**
     * Get the fields for this resource.
     *
     * @return Field[]
     */
    public function resolveFields(): array;

    /**
     * Get the meta attributes for this resource.
     *
     * @return Attribute[]
     */
    public function meta(): array;

    /**
     * Get the ID for a model.
     *
     * @param M $model
     * @param C $context
     */
    public function getId(object $model, Context $context): string;

    /**
     * Get the value of a field for a model.
     *
     * @param M $model
     * @param C $context
     */
    public function getValue(object $model, Field $field, Context $context): mixed;
}
