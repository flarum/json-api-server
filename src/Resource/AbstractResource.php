<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;

/**
 * @template M of object
 * @template C of Context
 * @implements Resource<M, C>
 * @implements Collection<M, C>
 */
abstract class AbstractResource implements Resource, Collection
{
    public function name(): string
    {
        return $this->type();
    }

    public function resources(): array
    {
        return [$this->type()];
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function resource(object $model, Context $context): ?string
    {
        return $this->type();
    }

    public function endpoints(): array
    {
        return [];
    }

    public function resolveEndpoints(): array
    {
        return $this->endpoints();
    }

    public function fields(): array
    {
        return [];
    }

    public function resolveFields(): array
    {
        return $this->fields();
    }

    public function meta(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [];
    }

    public function sorts(): array
    {
        return [];
    }

    public function resolveSorts(): array
    {
        return $this->sorts();
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function getId(object $model, Context $context): string
    {
        return $model->id;
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function getValue(object $model, Field $field, Context $context): mixed
    {
        return $model->{$field->property ?: $field->name} ?? null;
    }

    /**
     * @param C $context
     */
    public function id(Context $context): ?string
    {
        return $context->extractIdFromPath($context);
    }
}
