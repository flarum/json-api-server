<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;

abstract class EloquentBuffer
{
    private static array $buffer = [];

    public static function add(Model $model, string $relationName, ?array $aggregate = null): void
    {
        static::$buffer[get_class($model)][$relationName][$aggregate ? $aggregate['column'].$aggregate['function'] : 'normal'][] = $model;
    }

    public static function getBuffer(Model $model, string $relationName, ?array $aggregate = null): ?array
    {
        return static::$buffer[get_class($model)][$relationName][$aggregate ? $aggregate['column'].$aggregate['function'] : 'normal'] ?? null;
    }

    public static function setBuffer(Model $model, string $relationName, ?array $aggregate, array $buffer): void
    {
        static::$buffer[get_class($model)][$relationName][$aggregate ? $aggregate['column'].$aggregate['function'] : 'normal'] = $buffer;
    }

    /**
     * @param array{relation: string, column: string, function: string, constrain: Closure}|null $aggregate
     */
    public static function load(
        Model $model,
        string $relationName,
        Relationship $relationship,
        Context $context,
        ?array $aggregate = null,
    ): void {
        if (!($models = static::getBuffer($model, $relationName, $aggregate))) {
            return;
        }

        $loader = function ($relation) use (
            $model,
            $relationName,
            $relationship,
            $context,
            $aggregate,
        ) {
            $query = $relation instanceof Relation ? $relation->getQuery() : $relation;

            // When loading the relationship, we need to scope the query
            // using the scopes defined in the related API resource – there
            // may be multiple if this is a polymorphic relationship. We
            // start by getting the resource types this relationship
            // could possibly contain.
            $resources = $context->api->resources;

            if ($type = $relationship->collections) {
                $resources = array_intersect_key($resources, array_flip($type));
            }

            // Now, construct a map of model class names -> scoping
            // functions. This will be provided to the MorphTo::constrain
            // method in order to apply type-specific scoping.
            $constrain = [];

            foreach ($resources as $resource) {
                $modelClass = get_class($resource->newModel($context));

                if ($resource instanceof EloquentResource && !isset($constrain[$modelClass])) {
                    $constrain[$modelClass] = function (Builder $query) use ($resource, $context, $relationship, $relation, $aggregate) {
                        $resource->scope($query, $context);

                        // Limiting relationship results is only possible on Laravel 11 or later,
                        // or if the model uses the \Staudenmeir\EloquentEagerLimit\HasEagerLimit trait.
                        if (! $aggregate && $relationship instanceof ToMany && method_exists($relation, 'limit') && ! empty($relationship->limit)) {
                            $relation->limit($relationship->limit);
                        }

                        if ($aggregate && ! empty($aggregate['constrain'])) {
                            ($aggregate['constrain'])($query, $context);
                        }

                        if ($relationship instanceof ToMany && $relationship->constrain) {
                            ($relationship->constrain)($query, $context);
                        }
                    };
                }
            }

            if ($relation instanceof MorphTo) {
                $relation->constrain($constrain);
            } elseif ($constrain) {
                reset($constrain)($query);
            }

            return $query;
        };

        $collection = $model->newCollection($models);

        if (! $aggregate) {
            $collection->load([$relationName => $loader]);
        } else {
            $collection->loadAggregate([$relationName => $loader], $aggregate['column'], $aggregate['function']);
        }

        static::setBuffer($model, $relationName, $aggregate, []);
    }
}
