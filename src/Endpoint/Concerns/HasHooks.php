<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Closure;
use Tobyz\JsonApiServer\Context;

trait HasHooks
{
    protected array $before = [];
    protected array $after = [];

    public function before(Closure $callback): static
    {
        $this->before[] = $callback;

        return $this;
    }

    public function after(Closure $callback): static
    {
        $this->after[] = $callback;

        return $this;
    }

    protected function callBeforeHook(Context $context): void
    {
        foreach ($this->before as $before) {
            $before($context);
        }
    }

    protected function callAfterHook(Context $context, mixed $data): mixed
    {
        foreach ($this->after as $after) {
            $data = $after($context, $data);
        }

        return $data;
    }
}
