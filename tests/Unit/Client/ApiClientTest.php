<?php

namespace Tests\Unit\Client;

use PHPUnit\Framework\Constraint\ArraySubset;
use SeoApi\Client\ApiClient;
use Tests\Lib\JsonPayload;
use Tests\Lib\RequestTesterTrait;
use Tests\Unit\UnitTestCase;
use function array_merge;

class ApiClientTest extends UnitTestCase
{
    use RequestTesterTrait;

    private const BASE_URL = 'https://testhost';
    private const SAMPLE_JSON_RESPONSE = ['region1', 'region2', ['nestedData' => [1, 2, 3]]];
    private const VALID_SESSION_ID = '07d38bbc-1a97-4f82-acf7-fd0c5766e095';
    private const AUTH_TOKEN = 'test_token';

    protected function setUp(): void
    {
        $this->setupRequestTester();
    }

    /**
     * @test
     */
    public function getRegions()
    {
        $client = $this->getAuthenticatedClient();

        $queryFilter = 'москва';

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo('/google/regions/'))
                       ->withQuery(new ArraySubset(['q' => $queryFilter]))
        ;

        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $regions = $client->getRegions($queryFilter);

        self::assertEquals(self::SAMPLE_JSON_RESPONSE, $regions);
    }

    public function provideStatsRequests()
    {
        foreach (ApiClient::SEARCH_PLATFORMS as $platform) {
            foreach (ApiClient::STATS_PERIODS as $period) {
                yield [
                    $period,
                    $platform,
                    [
                        'report_type' => $period,
                    ],
                ];
            }
        }
    }

    /**
     * @test
     * @dataProvider provideStatsRequests
     *
     * @param string $period
     * @param string $platform
     * @param array $expectQuery
     */
    public function getAggregateStatsReport(string $period, string $platform, array $expectQuery)
    {
        $client = $this->getAuthenticatedClient();

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo("/{$platform}/user/report/"))
                       ->withQuery(new ArraySubset($expectQuery))
        ;
        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $data = $client->getAggregateStatsReport($platform, $period);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $data);
    }

    public function provideDailyStatsRequests()
    {
        $year = 2019;
        $month = 12;
        foreach (ApiClient::SEARCH_PLATFORMS as $platform) {
            yield [
                $platform,
                $year,
                $month,
                [
                    'year' => $year,
                    'month' => $month,
                ],
            ];
        }
    }

    /**
     * @test
     * @dataProvider provideDailyStatsRequests
     *
     * @param string $platform
     * @param int $year
     * @param int $month
     * @param string $expectPath
     * @param array $expectQuery
     */
    public function getDailyStatsReport(string $platform, int $year, int $month, array $expectQuery)
    {
        $client = $this->getAuthenticatedClient();

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo("/{$platform}/user/report/daily/"))
                       ->withQuery(new ArraySubset($expectQuery))
        ;
        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $data = $client->getDailyStatsReport($platform, $year, $month);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $data);
    }


    /**
     * @test
     */
    public function loadTasksWithJson()
    {
        $client = $this->getAuthenticatedClient();

        $pageSize = 100;
        $pagesTotal = 10;
        $queries = [
            [
                'query' => 'foo',
                'query_id' => 'query1',
                'numdoc' => 50,
                'total_pages' => 3,
                'region' => 77,
            ],
        ];

        $extraParams = [
            'domain' => 'google.ru',
            'is_mobile' => 1,
            'region' => 66,
            'params' => ['x' => 1, 'y' => 2],
        ];

        $payload = array_merge([
            'source' => 'google',
            'session_id' => self::VALID_SESSION_ID,
            'numdoc' => $pageSize,
            'total_pages' => $pagesTotal,
            'queries' => $queries,
        ], $extraParams);

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('POST'))
                       ->withPath(self::equalTo("/google/load_tasks/"))
                       ->withPayload(new JsonPayload($payload))
        ;

        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $sessionData = $client->loadTasks(
            'google',
            self::VALID_SESSION_ID,
            $pageSize,
            $pagesTotal,
            $queries,
            $extraParams
        );

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    /**
     * @test
     */
    public function getTasksSessionStatus()
    {
        $client = $this->getAuthenticatedClient();

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo("/google/session/".self::VALID_SESSION_ID."/"))
        ;
        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $sessionData = $client->getTasksSessionStatus('google', self::VALID_SESSION_ID);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    /**
     * @test
     */
    public function getTasksSessionResults()
    {
        $client = $this->getAuthenticatedClient();

        $limit = 1000;
        $offset = 2000;

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo("/google/results/".self::VALID_SESSION_ID."/"))
                       ->withQuery(self::equalTo([
                           'limit' => $limit,
                           'offset' => $offset,
                       ]))
        ;


        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));
        $sessionData = $client->getTasksSessionResults('google', self::VALID_SESSION_ID, $limit, $offset);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    private function getAuthenticatedClient(): ApiClient
    {
        return ApiClient::fromToken(self::AUTH_TOKEN, self::BASE_URL, $this->httpClientFactory);
    }
}
