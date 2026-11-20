<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-pagination
 */
class OffsetPaginationTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'articles',
                models: array_map(fn($i) => (object) ['id' => (string) $i], range(1, 100)),
                endpoints: [Index::make()->paginate()],
            ),
        );
    }

    public function test_can_request_limit_on_resource_collection()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams(['page' => ['limit' => '10']]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertCount(10, $data);
    }

    public function test_can_request_offset_on_resource_collection()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams(['page' => ['offset' => '5']]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertEquals('6', $data[0]['id'] ?? null);
    }

    public function test_pagination_links_are_correct_and_retain_query_parameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['offset' => '40'],
                'otherParam' => 'value',
            ]),
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertEquals('/articles?otherParam=value', $links['first'] ?? null);
        $this->assertEquals(
            '/articles?otherParam=value&page%5Boffset%5D=80',
            $links['last'] ?? null,
        );
        $this->assertEquals(
            '/articles?otherParam=value&page%5Boffset%5D=60',
            $links['next'] ?? null,
        );
        $this->assertEquals(
            '/articles?otherParam=value&page%5Boffset%5D=20',
            $links['prev'] ?? null,
        );
    }

    public function test_next_pagination_link_is_not_included_on_last_page()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['offset' => '80'],
            ]),
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertNull($links['next'] ?? null);
    }

    public function test_prev_pagination_link_is_not_included_on_first_page()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams(['page' => ['offset' => '0']]),
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertNull($links['prev'] ?? null);
    }

    public function test_can_request_integer_limit_and_offset()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['limit' => 10, 'offset' => 5],
            ]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertCount(10, $data);
        $this->assertEquals('6', $data[0]['id'] ?? null);
    }

    public function test_bad_request_error_if_limit_is_an_array()
    {
        $this->assertBadRequestForParameter('page[limit]', ['page' => ['limit' => ['5']]]);
    }

    public function test_bad_request_error_if_offset_is_an_array()
    {
        $this->assertBadRequestForParameter('page[offset]', ['page' => ['offset' => ['5']]]);
    }

    public function test_bad_request_error_if_offset_overflows_an_integer()
    {
        $this->assertBadRequestForParameter('page[offset]', [
            'page' => ['offset' => '99999999999999999999'],
        ]);
    }

    public function test_offset_with_leading_zeros()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['offset' => '05'],
            ]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertEquals('6', $data[0]['id'] ?? null);
    }

    public function test_offset_at_the_integer_boundary()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['offset' => (string) PHP_INT_MAX],
            ]),
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertBadRequestForParameter('page[offset]', [
            'page' => ['offset' => '9223372036854775808'],
        ]);
    }

    public function test_limit_with_leading_zeros()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['limit' => '05'],
            ]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertCount(5, $data);
    }

    public function test_overflowing_limit_is_capped_to_max_limit()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['limit' => '99999999999999999999'],
            ]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertCount(50, $data);
    }

    public function test_bad_request_error_if_uncapped_limit_overflows_an_integer()
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1']],
                endpoints: [Index::make()->paginate(20, 0)],
            ),
        );

        $this->assertBadRequestForParameter('page[limit]', [
            'page' => ['limit' => '99999999999999999999'],
        ]);
    }

    private function assertBadRequestForParameter(string $parameter, array $queryParams): void
    {
        try {
            $this->api->handle(
                $this->buildRequest('GET', '/articles')->withQueryParams($queryParams),
            );
        } catch (BadRequestException $e) {
            $this->assertEquals(
                $parameter,
                $e->getJsonApiErrors()[0]['source']['parameter'] ?? null,
            );

            return;
        }

        $this->fail('Expected a BadRequestException');
    }
}
