<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasHooks;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ValidatesData;
use Tobyz\JsonApiServer\Resource\Updatable;

class Update extends Endpoint
{
    use SavesData;
    use ValidatesData;
    use HasHooks;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'update');
    }

    public function setUp(): void
    {
        $this->route('PATCH', '/{id}')
            ->action(function (Context $context): object {
                $model = $context->model;

                $context = $context->withResource(
                    $resource = $context->resource($context->collection->resource($model, $context)),
                );

                if (!$resource instanceof Updatable) {
                    throw new RuntimeException(
                        sprintf('%s must implement %s', get_class($resource), Updatable::class),
                    );
                }

                $this->callBeforeHook($context);

                $data = $this->parseData($context);

                $this->assertFieldsValid($context, $data);
                $this->deserializeValues($context, $data);
                $this->assertDataValid($context, $data);
                $this->setValues($context, $data);

                $context = $context->withModel($model = $resource->updateAction($model, $context));

                $this->saveFields($context, $data);

                return $this->callAfterHook($context, $model);
            })
            ->beforeSerialization(function (Context $context, object $model) {
                $this->loadRelations(Collection::make([$model]), $context, $this->getInclude($context));
            });
    }
}
