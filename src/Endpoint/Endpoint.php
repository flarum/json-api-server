<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasEagerLoading;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use function Tobyz\JsonApiServer\json_api_response;

class Endpoint
{
    use ShowsResources;
    use FindsResources;
    use HasVisibility;
    use HasEagerLoading;

    public string $method;
    public string $path;

    protected ?Closure $action = null;
    protected ?Closure $response = null;
    protected array $beforeSerialization = [];

    public function __construct(
        public string $name
    ) {
    }

    public static function make(?string $name): static
    {
        $endpoint = new static($name);

        $endpoint->setUp();

        return $endpoint;
    }

    protected function setUp(): void
    {
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function response(Closure $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function route(string $method, string $path): static
    {
        $this->method = $method;
        $this->path = '/' . ltrim(rtrim($path, '/'), '/');

        return $this;
    }

    public function beforeSerialization(Closure $callback): static
    {
        $this->beforeSerialization[] = $callback;

        return $this;
    }

    public function process(Context $context): mixed
    {
        if (! $this->action) {
            throw new RuntimeException("No action defined for endpoint [".static::class."]");
        }

        return ($this->action)($context);
    }

    public function handle(Context $context): ?Response
    {
        if (! isset($this->method, $this->path)) {
            throw new RuntimeException("No route defined for endpoint [".static::class."]");
        }

        if (strtolower($context->method()) !== strtolower($this->method)) {
            throw new MethodNotAllowedException();
        }

        $context = $context->withModelId(
            $context->collection->id($context)
        );

        if ($context->modelId) {
            $context = $context->withModel(
                $this->findResource($context, $context->modelId)
            );
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        $data = $this->process($context);

        foreach ($this->beforeSerialization as $callback) {
            $callback($context, $data);
        }

        if ($this->response) {
            return ($this->response)($context, $data);
        }

        if ($context->model && $data instanceof $context->model) {
            return json_api_response($this->showResource($context, $data));
        }

        return null;
    }
}
