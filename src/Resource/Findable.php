<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

/**
 * @template M of object
 * @template C of Context
 */
interface Findable
{
    /**
     * Find a model with the given ID.
     *
     * @param C $context
     * @return M|null
     */
    public function find(string $id, Context $context): ?object;
}
