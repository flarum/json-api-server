<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

/**
 * @template M of object
 * @template C of Context
 */
interface Attachable
{
    /**
     * Attach a related model to a model.
     *
     * @param M $model
     * @param C $context
     */
    public function attach(
        object $model,
        Relationship $relationship,
        mixed $related,
        Context $context,
    ): void;

    /**
     * Detach a related model from a model.
     *
     * @param M $model
     * @param C $context
     */
    public function detach(
        object $model,
        Relationship $relationship,
        mixed $related,
        Context $context,
    ): void;
}
