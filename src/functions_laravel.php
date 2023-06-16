<?php

namespace Tobyz\JsonApiServer\Laravel;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Tobyz\JsonApiServer\Context;

function rules($rules, array $messages = [], array $customAttributes = []): Closure
{
    if (is_string($rules)) {
        $rules = [$rules];
    }

    return function (
        callable $fail,
        $value,
        Model $model,
        Context $context,
    ) use ($rules, $messages, $customAttributes) {
        $key = $context->field->name;

        $validatorRules = [$key => []];

        foreach ($rules as $k => $rule) {
            if (is_string($rule)) {
                $rule = str_replace('{id}', $model->getKey(), $rule);
            }

            if (!is_numeric($k)) {
                $validatorRules[$key . '.' . $k] = $rule;
            } else {
                $validatorRules[$key][] = $rule;
            }
        }

        $validation = Validator::make(
            $value !== null ? [$key => $value] : [],
            $validatorRules,
            $messages,
            $customAttributes,
        );

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $fail($message);
            }
        }
    };
}

function authenticated(): Closure
{
    return fn() => Auth::check();
}

function can(string $ability, ...$args): Closure
{
    return function ($arg) use ($ability, $args) {
        if ($arg instanceof Model) {
            array_unshift($args, $arg);
        }

        return Gate::allows($ability, $args);
    };
}
