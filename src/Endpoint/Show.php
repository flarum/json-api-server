<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasHooks;

class Show extends Endpoint
{
    use HasHooks;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'show');
    }

    public function setUp(): void
    {
        $this->route('GET', '/{id}')
            ->action(function (Context $context): object {
                $this->callBeforeHook($context);

                return $this->callAfterHook($context, $context->model);
            });
    }
}
