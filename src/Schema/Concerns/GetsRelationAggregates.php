<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Schema\Type\Number;

trait GetsRelationAggregates
{
    /**
     * @var array{relation: string, column: string, function: string}|null
     */
    public ?array $relationAggregate = null;

    public function relationAggregate(string $relation, string $column, string $function): static
    {
        if (! $this->type instanceof Number) {
            throw new \InvalidArgumentException('Relation aggregates can only be used with number attributes');
        }

        $this->relationAggregate = compact('relation', 'column', 'function');

        return $this;
    }

    public function countRelation(string $relation): static
    {
        return $this->relationAggregate($relation, '*', 'count');
    }

    public function sumRelation(string $relation, string $column): static
    {
        return $this->relationAggregate($relation, $column, 'sum');
    }

    public function avgRelation(string $relation, string $column): static
    {
        return $this->relationAggregate($relation, $column, 'avg');
    }

    public function minRelation(string $relation, string $column): static
    {
        return $this->relationAggregate($relation, $column, 'min');
    }

    public function maxRelation(string $relation, string $column): static
    {
        return $this->relationAggregate($relation, $column, 'max');
    }

    public function getRelationAggregate(): ?array
    {
        return $this->relationAggregate;
    }
}
