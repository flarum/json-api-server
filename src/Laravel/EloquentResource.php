<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\AbstractResource;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Resource\Findable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Paginatable;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Contracts\RelationAggregator;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Type\DateTime;

/**
 * @template M of Model
 * @template C of Context
 * @extends AbstractResource<M, C>
 * @implements Findable<M, C>
 * @implements Listable<M, C>
 * @implements Countable<C>
 * @implements Creatable<M, C>
 * @implements Updatable<M, C>
 * @implements Deletable<M, C>
 */
abstract class EloquentResource extends AbstractResource implements
    Findable,
    Listable,
    Countable,
    Paginatable,
    Creatable,
    Updatable,
    Deletable
{
    /**
     * @param M $model
     * @param C $context
     */
    public function resource(object $model, Context $context): ?string
    {
        $eloquentModel = $this->newModel($context);

        if ($model instanceof $eloquentModel) {
            return $this->type();
        }

        return null;
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function getId(object $model, Context $context): string
    {
        return $model->getKey();
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function getValue(object $model, Field $field, Context $context): mixed
    {
        if ($field instanceof Relationship) {
            return $this->getRelationshipValue($model, $field, $context);
        } else {
            return $this->getAttributeValue($model, $field, $context);
        }
    }

    /**
     * @param M $model
     * @param C $context
     */
    protected function getAttributeValue(Model $model, Field $field, Context $context)
    {
        if ($field instanceof RelationAggregator && ($aggregate = $field->getRelationAggregate())) {
            $relationName = $aggregate['relation'];

            if (! $model->isRelation($relationName)) {
                return $model->getAttribute($this->property($field));
            }

            $relationship = collect($context->fields($this))->first(fn ($f) => $f->name === $relationName);

            if (! $relationship) {
                throw new InvalidArgumentException("To use relation aggregates, the relationship field must be part of the resource. Missing field: $relationName for attribute $field->name.");
            }

            EloquentBuffer::add($model, $relationName, $aggregate);

            return function () use ($model, $relationName, $relationship, $field, $context, $aggregate) {
                EloquentBuffer::load($model, $relationName, $relationship, $context, $aggregate);

                return $model->getAttribute($this->property($field));
            };
        }

        return $model->getAttribute($this->property($field));
    }

    /**
     * @param M $model
     * @param C $context
     */
    protected function getRelationshipValue(Model $model, Relationship $field, Context $context)
    {
        $method = $this->method($field);

        if ($model->isRelation($method)) {
            $relation = $model->$method();

            // If this is a belongs-to relationship, and we only need to get the ID
            // for linkage, then we don't have to actually load the relation because
            // the ID is stored in a column directly on the model. We will mock up a
            // related model with the value of the ID filled.
            if ($relation instanceof BelongsTo && $context->include === null) {
                if ($key = $model->getAttribute($relation->getForeignKeyName())) {
                    if ($relation instanceof MorphTo) {
                        $morphType = $model->{$relation->getMorphType()};
                        $morphType = MorphTo::getMorphedModel($morphType) ?? $morphType;
                        $related = $relation->createModelByType($morphType);
                    } else {
                        $related = $relation->getRelated();
                    }

                    return $related->newInstance()->forceFill([$related->getKeyName() => $key]);
                }

                return null;
            }

            EloquentBuffer::add($model, $method);

            return function () use ($model, $method, $field, $context) {
                EloquentBuffer::load($model, $method, $field, $context);

                $data = $model->getRelation($method);

                return $data instanceof Collection ? $data->all() : $data;
            };
        }

        return $this->getAttributeValue($model, $field, $context);
    }

    /**
     * @param C $context
     */
    public function query(Context $context): object
    {
        $query = $this->newModel($context)->query();

        $this->scope($query, $context);

        return $query;
    }

    /**
     * Hook to scope a query for this resource.
     *
     * @param Builder<M> $query
     * @param C $context
     */
    public function scope(Builder $query, Context $context): void
    {
    }

    /**
     * @param Builder<M> $query
     * @param C $context
     */
    public function results(object $query, Context $context): iterable
    {
        return $query->get()->all();
    }

    /**
     * @param Builder<M> $query
     */
    public function paginate(object $query, OffsetPagination $pagination): void
    {
        $query->take($pagination->limit)->skip($pagination->offset);
    }

    /**
     * @param Builder<M> $query
     * @param C $context
     */
    public function count(object $query, Context $context): ?int
    {
        return $query->toBase()->getCountForPagination();
    }

    /**
     * @param C $context
     */
    public function find(string $id, Context $context): ?object
    {
        if ($id === null) {
            return null;
        }

        return $this->query($context)->find($id);
    }

    /**
     * @param M $model
     * @param C $context
     * @throws \Exception
     */
    public function setValue(object $model, Field $field, mixed $value, Context $context): void
    {
        if ($field instanceof Relationship) {
            $method = $this->method($field);
            $relation = $model->$method();

            // If this is a belongs-to relationship, then the ID is stored on the
            // model itself, so we can set it here.
            if ($relation instanceof BelongsTo) {
                $relation->associate($value);
            }

            return;
        }

        // Mind-blowingly, Laravel discards timezone information when storing
        // dates in the database. Since the API can receive dates in any
        // timezone, we will need to convert it to the app's configured
        // timezone ourselves before storage.
        if (
            $field instanceof Attribute &&
            $field->type instanceof DateTime &&
            $value instanceof \DateTimeInterface
        ) {
            $value = \DateTime::createFromInterface($value)->setTimezone(
                new \DateTimeZone(config('app.timezone')),
            );
        }

        $model->setAttribute($this->property($field), $value);
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function saveValue(object $model, Field $field, mixed $value, Context $context): void
    {
        if ($field instanceof ToMany) {
            $method = $this->method($field);
            $relation = $model->$method();

            if ($relation instanceof BelongsToMany) {
                $relation->sync(new Collection($value));
            }
        }
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function createAction(object $model, Context $context): object
    {
        if (method_exists($this, 'creating')) {
            $model = $this->creating($model, $context) ?: $model;
        }

        if (method_exists($this, 'saving')) {
            $model = $this->saving($model, $context) ?: $model;
        }

        $model = $this->create($model, $context);

        if (method_exists($this, 'saved')) {
            $model = $this->saved($model, $context) ?: $model;
        }

        if (method_exists($this, 'created')) {
            $model = $this->created($model, $context) ?: $model;
        }

        return $model;
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function create(object $model, Context $context): object
    {
        $this->saveModel($model, $context);

        return $model;
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function updateAction(object $model, Context $context): object
    {
        if (method_exists($this, 'updating')) {
            $model = $this->updating($model, $context) ?: $model;
        }

        if (method_exists($this, 'saving')) {
            $model = $this->saving($model, $context) ?: $model;
        }

        $this->update($model, $context);

        if (method_exists($this, 'saved')) {
            $model = $this->saved($model, $context) ?: $model;
        }

        if (method_exists($this, 'updated')) {
            $model = $this->updated($model, $context) ?: $model;
        }

        return $model;
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function update(object $model, Context $context): object
    {
        $this->saveModel($model, $context);

        return $model;
    }

    /**
     * @param M $model
     * @param C $context
     */
    protected function saveModel(Model $model, Context $context): void
    {
        $model->save();
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function deleteAction(object $model, Context $context): void
    {
        if (method_exists($this, 'deleting')) {
            $this->deleting($model, $context);
        }

        $this->delete($model, $context);

        if (method_exists($this, 'deleted')) {
            $this->deleted($model, $context);
        }
    }

    /**
     * @param M $model
     * @param C $context
     */
    public function delete(object $model, Context $context): void
    {
        $model->delete();
    }

    /**
     * Get the model property that a field represents.
     */
    protected function property(Field $field): string
    {
        return $field->property ?: Str::snake($field->name);
    }

    /**
     * Get the model method that a field represents.
     */
    protected function method(Field $field): string
    {
        return $field->property ?: $field->name;
    }
}
