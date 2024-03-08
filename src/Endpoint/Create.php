<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasHooks;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ValidatesData;
use Tobyz\JsonApiServer\Resource\Creatable;

use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\set_value;

class Create extends Endpoint
{
    use SavesData;
    use ValidatesData;
    use HasHooks;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'create');
    }

    public function setUp(): void
    {
        $this->route('POST', '/')
            ->action(function (Context $context): ?object {
                if (str_contains($context->path(), '/')) {
                    return null;
                }

                $collection = $context->collection;

                if (!$collection instanceof Creatable) {
                    throw new RuntimeException(
                        sprintf('%s must implement %s', get_class($collection), Creatable::class),
                    );
                }

                $this->callBeforeHook($context);

                $data = $this->parseData($context);

                $context = $context
                    ->withResource($resource = $context->resource($data['type']))
                    ->withModel($model = $collection->newModel($context));

                $this->assertFieldsValid($context, $data);
                $this->fillDefaultValues($context, $data);
                $this->deserializeValues($context, $data);
                $this->assertDataValid($context, $data);
                $this->setValues($context, $data);

                $context = $context->withModel($model = $resource->createAction($model, $context));

                $this->saveFields($context, $data);

                return $this->callAfterHook($context, $model);
            })
            ->beforeSerialization(function (Context $context, object $model) {
                $this->loadRelations(Collection::make([$model]), $context, $this->getInclude($context));
            })
            ->response(function (Context $context, object $model) {
                return json_api_response($document = $this->showResource($context, $model))
                    ->withStatus(201)
                    ->withHeader('Location', $document['data']['links']['self']);
            });
    }

    final protected function fillDefaultValues(Context $context, array &$data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field) && ($default = $field->default)) {
                set_value($data, $field, $default($context->withField($field)));
            }
        }
    }
}
