<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

interface Deletable extends Findable
{
    /**
     * Delete a model. With pre- and post-hooks.
     */
    public function deleteAction(object $model, Context $context): void;

    /**
     * Delete a model.
     */
    public function delete(object $model, Context $context): void;
}
