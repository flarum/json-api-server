<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\Schema\Field\Attribute;

trait ValidatesData
{
    /**
     * Assert that the field values within a data object pass validation.
     *
     * @throws UnprocessableEntityException
     */
    protected function assertDataValid(Context $context, array $data): void
    {
        $this->mutateDataBeforeValidation($context, $data);

        $collection = $context->collection;

        $rules = [
            'attributes' => [],
            'relationships' => [],
        ];

        $messages = [];
        $attributes = [];

        foreach ($context->fields($context->resource) as $field) {
            $writable = $field->isWritable($context->withField($field));

            if (! $writable) {
                continue;
            }

            $type = $field instanceof Attribute ? 'attributes' : 'relationships';

            $rules[$type] = array_merge($rules[$type], $field->getValidationRules($context));
            $messages = array_merge($messages, $field->getValidationMessages($context));
            $attributes = array_merge($attributes, $field->getValidationAttributes($context));
        }

        if (method_exists($collection, 'validationFactory')) {
            $factory = $collection->validationFactory();
        } else {
            $loader = new ArrayLoader();
            $translator = new Translator($loader, 'en');
            $factory = new Factory($translator);
        }

        $attributeValidator = $factory->make($data['attributes'], $rules['attributes'], $messages, $attributes);
        $relationshipValidator = $factory->make($data['relationships'], $rules['relationships'], $messages, $attributes);

        $this->validate('attributes', $attributeValidator);
        $this->validate('relationships', $relationshipValidator);
    }

    /**
     * @throws UnprocessableEntityException if any fields do not pass validation.
     */
    protected function validate(string $type, Validator $validator): void
    {
        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[] = [
                    'source' => ['pointer' => "/data/$type/$field"],
                    'detail' => implode(' ', $messages),
                ];
            }

            throw new UnprocessableEntityException($errors);
        }
    }

    protected function mutateDataBeforeValidation(Context $context, array $data): array
    {
        if (method_exists($context->resource, 'mutateDataBeforeValidation')) {
            return $context->resource->mutateDataBeforeValidation($context, $data);
        }

        return $data;
    }
}
