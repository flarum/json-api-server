<?php

namespace Tobyz\JsonApiServer;

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Endpoint\Endpoint;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use WeakMap;
use function Laravel\Prompts\error;

class Context
{
    public ?Collection $collection = null;
    public ?Resource $resource = null;
    public ?Endpoint $endpoint = null;
    public ?object $query = null;
    public ?Serializer $serializer = null;
    public int|string|null $modelId = null;
    public mixed $model = null;
    public ?Field $field = null;
    public ?array $include = null;
    public ?array $requestIncludes = null;

    private ?array $body;
    private ?string $path;

    private WeakMap $fields;
    private WeakMap $sparseFields;

    public function __construct(public JsonApi $api, public ServerRequestInterface $request)
    {
        $this->fields = new WeakMap();
        $this->sparseFields = new WeakMap();
    }

    /**
     * Get the value of a query param.
     */
    public function queryParam(string $name, $default = null)
    {
        return $this->request->getQueryParams()[$name] ?? $default;
    }

    /**
     * Get the request method.
     */
    public function method(): string
    {
        return $this->request->getMethod();
    }

    /**
     * Get the request path relative to the API base path.
     */
    public function path(): string
    {
        return $this->path ??= trim(
            $this->api->stripBasePath($this->request->getUri()->getPath()),
            '/',
        );
    }

    /**
     * Get the parsed JSON:API payload.
     */
    public function body(): ?array
    {
        return $this->body ??=
            (array) $this->request->getParsedBody() ?:
            json_decode($this->request->getBody()->getContents(), true);
    }

    /**
     * Get a resource by its type.
     */
    public function resource(string $type): Resource
    {
        return $this->api->getResource($type);
    }

    /**
     * Get the fields for the given resource, keyed by name.
     *
     * @return array<string, Field>
     */
    public function fields(Resource $resource): array
    {
        if (isset($this->fields[$resource])) {
            return $this->fields[$resource];
        }

        $fields = [];

        foreach ($resource->resolveFields() as $field) {
            $fields[$field->name] = $field;
        }

        return $this->fields[$resource] = $fields;
    }

    /**
     * Get only the requested fields for the given resource, keyed by name.
     *
     * @return array<string, Field>
     */
    public function sparseFields(Resource $resource): array
    {
        if (isset($this->sparseFields[$resource])) {
            return $this->sparseFields[$resource];
        }

        $fields = $this->fields($resource);

        if ($requested = $this->queryParam('fields')[$resource->type()] ?? null) {
            $requested = is_array($requested) ? $requested : explode(',', $requested);

            $fields = array_intersect_key($fields, array_flip($requested));
        }

        return $this->sparseFields[$resource] = $fields;
    }

    /**
     * Determine whether a field has been requested in a sparse fieldset.
     */
    public function fieldRequested(string $type, string $field, bool $default = true): bool
    {
        if ($requested = $this->queryParam('fields')[$type] ?? null) {
            return in_array($field, explode(',', $requested));
        }

        return $default;
    }

    /**
     * Determine whether a sort field has been requested.
     */
    public function sortRequested(string $field): bool
    {
        if ($sort = $this->queryParam('sort')) {
            foreach (parse_sort_string($sort) as [$name, $direction]) {
                if ($name === $field) {
                    return true;
                }
            }
        }

        return false;
    }

    public function withRequest(ServerRequestInterface $request): static
    {
        $new = clone $this;
        $new->request = $request;
        $new->sparseFields = new WeakMap();
        $new->body = null;
        $new->path = null;
        $this->requestIncludes = null;
        return $new;
    }

    public function withCollection(Collection $collection): static
    {
        $new = clone $this;
        $new->collection = $collection;
        return $new;
    }

    public function withResource(Resource $resource): static
    {
        $new = clone $this;
        $new->resource = $resource;
        return $new;
    }

    public function withEndpoint(Endpoint $endpoint): static
    {
        $new = clone $this;
        $new->endpoint = $endpoint;
        return $new;
    }

    public function withQuery(object $query): static
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function withSerializer(Serializer $serializer): static
    {
        $new = clone $this;
        $new->serializer = $serializer;
        return $new;
    }

    public function withModelId(int|string|null $id): static
    {
        $new = clone $this;
        $new->modelId = $id;
        return $new;
    }

    public function withModel(mixed $model): static
    {
        $new = clone $this;
        $new->model = $model;
        return $new;
    }

    public function withField(Field $field): static
    {
        $new = clone $this;
        $new->field = $field;
        return $new;
    }

    public function withInclude(?array $include): static
    {
        $new = clone $this;
        $new->include = $include;
        return $new;
    }

    public function withRequestIncludes(array $requestIncludes): static
    {
        $new = clone $this;
        $new->requestIncludes = $requestIncludes;
        return $new;
    }

    public function extractIdFromPath(Context $context): ?string
    {
        $currentPath = trim($context->path(), '/');
        $path = trim($context->collection->name() . $this->endpoint->path, '/');

        if (!str_contains($path, '{id}')) {
            return null;
        }

        $segments = explode('/', $path);
        $idSegmentIndex = array_search('{id}', $segments);
        $currentPathSegments = explode('/', $currentPath);

        return $currentPathSegments[$idSegmentIndex] ?? null;
    }
}
