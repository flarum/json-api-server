<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

/**
 * @template M of object
 * @template C of Context
 */
interface Deletable extends Findable
{
    /**
     * Delete a model. With pre- and post-hooks.
     *
     * @param M $model
     * @param C $context
     */
    public function deleteAction(object $model, Context $context): void;

    /**
     * Delete a model.
     *
     * @param M $model
     * @param C $context
     */
    public function delete(object $model, Context $context): void;
}
