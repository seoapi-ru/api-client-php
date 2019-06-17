<?php

namespace Tests\Unit\Client;

use PHPUnit\Framework\Constraint\ArraySubset;
use SeoApi\Client\ApiClient;
use Tests\Lib\RequestTesterTrait;
use Tests\Unit\UnitTestCase;


class ApiClientTest extends UnitTestCase
{
    use RequestTesterTrait;

    private const BASE_URL = 'https://testhost';
    private const SAMPLE_JSON_RESPONSE = ['region1', 'region2', ['nestedData' => [1, 2, 3]]];
    private const VALID_SESSION_ID = '07d38bbc-1a97-4f82-acf7-fd0c5766e095';
    private const AUTH_TOKEN = 'test_token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRequestTester();
    }

    /**
     * @test
     */
    public function getRegions()
    {
        $client = $this->getAuthenticatedClient();

        $queryFilter = $this->faker->word;

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
     * @param array $query
     */
    public function getAggregateStatsReport(string $period, string $platform, array $query)
    {
        $client = $this->getAuthenticatedClient();

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo("/{$platform}/user/report/"))
                       ->withQuery(new ArraySubset($query))
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
     * @param array $query
     */
    public function getDailyStatsReport(string $platform, int $year, int $month, array $query)
    {
        $client = $this->getAuthenticatedClient();

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo("/{$platform}/user/report/daily/"))
                       ->withQuery(new ArraySubset($query))
        ;
        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $data = $client->getDailyStatsReport($platform, $year, $month);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $data);
    }


    private function getAuthenticatedClient(): ApiClient
    {
        return ApiClient::fromToken(self::AUTH_TOKEN, self::BASE_URL, $this->httpClientFactory);
    }
}
