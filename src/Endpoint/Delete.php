<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Nyholm\Psr7\Response;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasHooks;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

use function Tobyz\JsonApiServer\json_api_response;

class Delete extends Endpoint
{
    use HasMeta;
    use HasHooks;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'delete');
    }

    public function setUp(): void
    {
        $this->route('DELETE', '/{id}')
            ->action(function (Context $context) {
                $model = $context->model;

                $context = $context->withResource(
                    $resource = $context->resource($context->collection->resource($model, $context)),
                );

                if (!$resource instanceof Deletable) {
                    throw new RuntimeException(
                        sprintf('%s must implement %s', get_class($resource), Deletable::class),
                    );
                }

                $this->callBeforeHook($context);

                $resource->deleteAction($model, $context);

                $this->callAfterHook($context, $model);

                return null;
            })
            ->response(function (Context $context) {
                if ($meta = $this->serializeMeta($context)) {
                    return json_api_response(['meta' => $meta]);
                }

                return new Response(204);
            });
    }
}
