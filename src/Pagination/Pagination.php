<?php

namespace Tobyz\JsonApiServer\Pagination;

interface Pagination
{
    public function meta(): array;

    public function links(int $count, ?int $total): array;

    public function apply($query): void;
}
