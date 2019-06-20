<?php

namespace Tests\Unit\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use SeoApi\Client\ApiClient;
use SeoApi\Client\Session\QueryBuilder;
use SeoApi\Client\Session\SessionBuilder;
use SeoApi\Client\Session\SessionResult;
use Tests\Lib\RequestTesterTrait;
use Tests\Unit\UnitTestCase;
use function array_slice;
use function iterator_count;

class SessionResultTest extends UnitTestCase
{
    use RequestTesterTrait;

    private const SESSION_ID = '07d38bbc-1a97-4f82-acf7-fd0c5766e095';
    private const RESULTS_COUNT = 350;
    private const PAGE_SIZE = 100;
    private const PAGES_COUNT = 4;
    private const PLATFORM = 'google';

    /** @var ClientInterface|MockObject */
    private $httpClientMock;
    /** @var ApiClient */
    private $apiClient;
    /** @var SessionBuilder */
    private $session;

    protected function setUp()
    {
        parent::setUp();

        $this->setupRequestTester();

        $this->apiClient = ApiClient::fromToken('test_token', 'http://foo', $this->httpClientFactory);
        $this->session = (new SessionBuilder(
            self::SESSION_ID,
            self::PLATFORM,
            $this->faker->numberBetween(1, 10),
            $this->faker->numberBetween(20, 30)
        ))->addQuery(new QueryBuilder('test'));
    }

    /**
     * @test
     */
    public function callsResultsEndpoint()
    {
        $resultsPagesExpected = $this->expectPaginatedResponses();
        $callsCount = 0;
        $results = SessionResult::iterateSessionResults($this->session, $this->apiClient, self::PAGE_SIZE);

        foreach ($results as $page => $resultSet) {
            self::assertEquals($resultsPagesExpected[$page], $resultSet);
            self::assertLessThanOrEqual(self::PAGE_SIZE, count($resultSet));
            $callsCount++;
        }

        self::assertSame(self::PAGES_COUNT, $callsCount);
    }

    /**
     * @test
     */
    public function noIterationsWhenNoResults()
    {
        $results = SessionResult::iterateSessionResults($this->session, $this->apiClient, self::PAGE_SIZE);

        $this->expectTasksResultsRequest(
            [
                'session-id' => self::SESSION_ID,
                'limit' => (string)self::PAGE_SIZE,
                'offset' => '0',
            ],
            self::jsonOkResponse(['total' => 0, 'results' => []])
        );

        self::assertSame(0, iterator_count($results));
    }

    private function expectPaginatedResponses(): array
    {
        $resultsExpected = $this->generateExpectedData();
        $resultsPagesExpected = [];
        for ($page = 0; $page < self::PAGES_COUNT; $page++) {
            $offset = self::PAGE_SIZE * $page;

            $resultsPageData = array_slice($resultsExpected, $offset, self::PAGE_SIZE);
            $resultsPagesExpected[] = $resultsPageData;

            $query = [
                'session-id' => self::SESSION_ID,
                'limit' => (string)self::PAGE_SIZE,
                'offset' => (string)$offset,
            ];
            $responseData = [
                'total' => self::RESULTS_COUNT,
                'results' => $resultsPageData,
            ];

            $this->expectTasksResultsRequest($query, self::jsonOkResponse($responseData));
        }

        return $resultsPagesExpected;
    }

    private function generateExpectedData(): array
    {
        $resultsExpected = [];
        for ($i = 0; $i < self::RESULTS_COUNT; $i++) {
            $resultsExpected[] = [
                "query" => $this->faker->sentence(2),
            ];
        }

        return $resultsExpected;
    }

    private function expectTasksResultsRequest(array $query, Response $response): void
    {
        $path = sprintf("/%s/results/%s/", self::PLATFORM, self::SESSION_ID);
        $request = self::expectRequest()
                       ->withPath(self::equalTo($path))
        ;

        $this->expectResponse(
            $request->withQuery(self::equalTo($query)),
            $response
        );
    }

}