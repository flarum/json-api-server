<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

/**
 * @template M of object
 * @template C of Context
 */
interface Collection
{
    /**
     * Get the collection name.
     */
    public function name(): string;

    /**
     * Get the resources contained within this collection.
     *
     * @return string[]
     */
    public function resources(): array;

    /**
     * Get the name of the resource that represents the given model.
     *
     * @param M $model
     * @param C $context
     */
    public function resource(object $model, Context $context): ?string;

    /**
     * The collection's endpoints.
     */
    public function endpoints(): array;

    /**
     * Resolve the endpoints for this collection.
     */
    public function resolveEndpoints(): array;

    /**
     * Get the model ID being handled by this collection.
     *
     * @param C $context
     */
    public function id(Context $context): ?string;
}
