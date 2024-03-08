<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;

/**
 * @template M of object
 * @template C of Context
 */
interface Creatable
{
    /**
     * Get a new model instance.
     *
     * @param C $context
     * @return M
     */
    public function newModel(Context $context): object;

    /**
     * Set a field value on the model instance.
     *
     * @param M $model
     * @param C $context
     */
    public function setValue(object $model, Field $field, mixed $value, Context $context): void;

    /**
     * Persist a field value on a model instance to storage.
     *
     * @param M $model
     * @param C $context
     */
    public function saveValue(object $model, Field $field, mixed $value, Context $context): void;

    /**
     * Persist a new model instance to storage. With pre- and post-hooks.
     *
     * @param M $model
     * @param C $context
     */
    public function createAction(object $model, Context $context): object;

    /**
     * Persist a new model instance to storage.
     *
     * @param M $model
     * @param C $context
     */
    public function create(object $model, Context $context): object;
}
