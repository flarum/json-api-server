<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;
use Tobyz\JsonApiServer\Schema\Sort;

/**
 * @template M of object
 * @template C of Context
 */
interface Listable
{
    /**
     * Create a query object for the current request.
     *
     * @param C $context
     */
    public function query(Context $context): object;

    /**
     * Get results from the given query.
     *
     * @param C $context
     */
    public function results(object $query, Context $context): iterable;

    /**
     * Filters that can be applied to the resource list.
     *
     * @return Filter[]
     */
    public function filters(): array;

    /**
     * Sorts that can be applied to the resource list.
     *
     * @return Sort[]
     */
    public function sorts(): array;

    /**
     * Resolve the sorts for this resource.
     */
    public function resolveSorts(): array;
}
