<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

/**
 * @template C of Context
 */
interface Countable extends Listable
{
    /**
     * Count the models for the given query.
     *
     * @param C $context
     */
    public function count(object $query, Context $context): ?int;
}
