<?php

namespace Tobyz\JsonApiServer\Schema\Field;

class Str extends Attribute
{
    public int $minLength = 0;
    public ?int $maxLength = null;
    public ?string $pattern = null;
    public ?string $format = null;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->serialize(static fn($value) => (string) $value);

        $this->deserialize(static fn($value) => (string) $value);

        $this->validate(function (mixed $value, callable $fail): void {
            if (!is_string($value)) {
                $fail('must be a string');
                return;
            }

            if (strlen($value) < $this->minLength) {
                $fail(sprintf('must be at least %d characters', $this->minLength));
            }

            if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
                $fail(sprintf('must be no more than %d characters', $this->maxLength));
            }

            if (
                $this->pattern &&
                !preg_match('/' . str_replace('/', '\/', $this->pattern) . '/', $value)
            ) {
                $fail(sprintf('must match the pattern %s', $this->pattern));
            }
        });
    }

    public function minLength(int $characters): static
    {
        $this->minLength = $characters;

        return $this;
    }

    public function maxLength(?int $characters): static
    {
        $this->maxLength = $characters;

        return $this;
    }

    public function pattern(?string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function format(?string $format): static
    {
        $this->format = $format;

        return $this;
    }
}