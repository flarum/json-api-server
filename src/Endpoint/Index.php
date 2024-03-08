<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasHooks;
use Tobyz\JsonApiServer\Endpoint\Concerns\IncludesData;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\Serializer;

use function Tobyz\JsonApiServer\apply_filters;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\parse_sort_string;

class Index extends Endpoint
{
    use HasMeta;
    use IncludesData;
    use HasHooks;

    public Closure $paginationResolver;
    public ?string $defaultSort = null;
    protected ?Closure $query = null;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->paginationResolver = fn() => null;
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'index');
    }

    public function query(?Closure $query): static
    {
        $this->query = $query;

        return $this;
    }

    protected function setUp(): void
    {
        $this->route('GET', '/')
            ->action(function (Context $context) {
                if (str_contains($context->path(), '/')) {
                    return null;
                }

                $collection = $context->collection;

                if (!$collection instanceof Listable) {
                    throw new RuntimeException(
                        sprintf('%s must implement %s', get_class($collection), Listable::class),
                    );
                }

                $this->callBeforeHook($context);

                $query = $collection->query($context);

                $pagination = ($this->paginationResolver)($context);

                if ($this->query) {
                    $context = ($this->query)($query, $pagination, $context);

                    if (! $context instanceof Context) {
                        throw new RuntimeException('The Index endpoint query closure must return a Context instance.');
                    }
                } else {
                    $context = $context->withQuery($query);

                    $this->applySorts($query, $context);
                    $this->applyFilters($query, $context);

                    if ($pagination) {
                        $pagination->apply($query);
                    }
                }

                $meta = $this->serializeMeta($context);

                if (
                    $collection instanceof Countable &&
                    !is_null($total = $collection->count($query, $context))
                ) {
                    $meta['page']['total'] = $total;
                }

                $models = $collection->results($query, $context);

                $models = $this->callAfterHook($context, $models);

                $total ??= null;

                return compact('models', 'meta', 'pagination', 'total');
            })
            ->beforeSerialization(function (Context $context, array $results) {
                $this->loadRelations(Collection::make($results['models']), $context, $this->getInclude($context));
            })
            ->response(function (Context $context, array $results): Response {
                $collection = $context->collection;

                ['models' => $models, 'meta' => $meta, 'pagination' => $pagination, 'total' => $total] = $results;

                $serializer = new Serializer($context);

                $include = $this->getInclude($context);

                foreach ($models as $model) {
                    $serializer->addPrimary(
                        $context->resource($collection->resource($model, $context)),
                        $model,
                        $include,
                    );
                }

                [$data, $included] = $serializer->serialize();

                $links = [];

                if ($pagination) {
                    $meta['page'] = array_merge($meta['page'] ?? [], $pagination->meta());
                    $links = array_merge($links, $pagination->links(count($data), $total));
                }

                return json_api_response(compact('data', 'included', 'meta', 'links'));
            });
    }

    public function paginate(int $defaultLimit = 20, int $maxLimit = 50): static
    {
        $this->paginationResolver = fn(Context $context) => new OffsetPagination(
            $context,
            $defaultLimit,
            $maxLimit,
        );

        return $this;
    }

    public function defaultSort(?string $defaultSort): static
    {
        $this->defaultSort = $defaultSort;

        return $this;
    }

    final protected function applySorts($query, Context $context): void
    {
        if (!($sortString = $context->queryParam('sort', $this->defaultSort))) {
            return;
        }

        $sorts = $context->collection->resolveSorts();

        foreach (parse_sort_string($sortString) as [$name, $direction]) {
            foreach ($sorts as $field) {
                if ($field->name === $name && $field->isVisible($context)) {
                    $field->apply($query, $direction, $context);
                    continue 2;
                }
            }

            throw (new BadRequestException("Invalid sort: $name"))->setSource([
                'parameter' => 'sort',
            ]);
        }
    }

    final protected function applyFilters($query, Context $context): void
    {
        if (!($filters = $context->queryParam('filter'))) {
            return;
        }

        if (!is_array($filters)) {
            throw (new BadRequestException('filter must be an array'))->setSource([
                'parameter' => 'filter',
            ]);
        }

        try {
            apply_filters($query, $filters, $context->collection, $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['parameter' => 'filter']);
        }
    }
}
