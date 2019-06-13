<?php

namespace Tests\Unit\Client;

use PHPUnit\Framework\Constraint\ArraySubset;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use SeoApi\Client\ApiClient;
use SeoApi\Client\Exception\BadResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Lib\HttpResponseMock;
use Tests\Unit\UnitTestCase;
use function array_merge;

class ApiClientTest extends UnitTestCase
{
    private const BASE_URL = 'https://testhost';
    private const USERNAME = 'test_username';
    private const PASSWORD = 'test_password';
    private const AUTH_USER_RESPONSE = [
        "user" => [
            "id" => 6,
            "token" => self::VALID_TOKEN,
        ],
    ];
    private const VALID_TOKEN = 'c768e683bf91b13478d5137713cc638f113600';
    private const SAMPLE_JSON_RESPONSE = ['region1', 'region2', ['nestedData' => [1, 2, 3]]];
    const VALID_SESSION_ID = '07d38bbc-1a97-4f82-acf7-fd0c5766e095';

    /** @var MockObject|HttpClientInterface */
    private $httpClientMock;
    /** @var int */
    private $requestCounter;

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->resetRequestExpectationsCounter();
    }

    /**
     * @test
     */
    public function createdFromCredentials()
    {
        $this->expectValidHttpAuthentication();
        $client = $this->authenticateClient();

        self::assertInstanceOf(ApiClient::class, $client);
    }

    public function createdFromToken()
    {
        $this->expectNoHttpAuthentication();
        $client = ApiClient::fromToken(self::VALID_TOKEN, self::BASE_URL, $this->httpClientMock);

        self::assertInstanceOf(ApiClient::class, $client);
    }

    private function authenticateClient(): ApiClient
    {
        $apiClient = ApiClient::fromCredentials(self::USERNAME, self::PASSWORD, self::BASE_URL, $this->httpClientMock);
        $this->resetRequestExpectationsCounter();

        return $apiClient;
    }

    private function resetRequestExpectationsCounter(): void
    {
        $this->requestCounter = 0;
    }

    private function expectValidHttpAuthentication(): self
    {
        $this->expectSingleRequest()
             ->with('POST', self::BASE_URL.'/user/obtain_token/', new ArraySubset([
                'body' => [
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ],
            ]))
             ->willReturn(new HttpResponseMock(200, self::AUTH_USER_RESPONSE))
        ;

        return $this;
    }

    public function provideExpectedFailCodes()
    {
        foreach (range(400, 499) as $code) {
            yield [$code];
        }
        foreach (range(500, 599) as $code) {
            yield [$code];
        }
    }

    /**
     * @test
     * @dataProvider provideExpectedFailCodes
     * @param int $code
     */
    public function failsAuthentication(int $code)
    {
        $badUsername = 'foo';
        $badPassword = 'bar';

        $this->expectFailedHttpAuthentication(self::BASE_URL, $badUsername, $badPassword, $code);
        $this->expectException(BadResponseException::class);

        ApiClient::fromCredentials($badUsername, $badPassword, self::BASE_URL, $this->httpClientMock);
    }

    private function expectFailedHttpAuthentication(
        string $baseUrl,
        string $badUsername,
        string $badPassword,
        int $code
    ): void {
        $this->httpClientMock->expects(self::once())
            ->method('request')
            ->with('POST', $baseUrl.'/user/obtain_token/', new ArraySubset([
                'body' => [
                    'username' => $badUsername,
                    'password' => $badPassword,
                ],
            ]))
            ->willReturn(new HttpResponseMock($code, []))
        ;
    }

    public function providePublicMethods()
    {
        return [
            ['getRegions', self::SAMPLE_JSON_RESPONSE, 'москва'],
            ['getAggregateStatsReport', self::SAMPLE_JSON_RESPONSE, 'google', 'today'],
            ['getDailyStatsReport', self::SAMPLE_JSON_RESPONSE, 'google', 2019, 6],
        ];
    }

    /**
     * @test
     * @dataProvider providePublicMethods
     * @param string $method
     * @param mixed $expectReturn
     * @param array $args
     */
    public function signsEachRequest(string $method, $expectReturn, ...$args)
    {
        $this->expectValidHttpAuthentication()
            ->thenExpectSingleRequest()
            ->with(
                self::anything(),
                self::anything(),
                new ArraySubset([
                    'headers' => ['Authorization' => 'Token '.self::VALID_TOKEN],
                ],
                    true)
            )->willReturn(new HttpResponseMock(200, $expectReturn))
        ;

        $this->authenticateClient()->$method(...$args);
    }

    /**
     * @test
     */
    public function getRegions()
    {
        $queryFilter = 'москва';

        $this->expectValidHttpAuthentication()
             ->thenExpectGetRequest('/google/regions/', ['q' => $queryFilter], self::SAMPLE_JSON_RESPONSE)
        ;

        $client = $this->authenticateClient();
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
     * @param string $expectPath
     * @param array $expectQuery
     */
    public function getAggregateStatsReport(string $period, string $platform, array $expectQuery)
    {
        $this->expectValidHttpAuthentication()
             ->thenExpectGetRequest("/{$platform}/user/report/", $expectQuery, self::SAMPLE_JSON_RESPONSE)
        ;

        $client = $this->authenticateClient();
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
        $this->expectValidHttpAuthentication()
             ->thenExpectGetRequest("/{$platform}/user/report/daily/", $expectQuery, self::SAMPLE_JSON_RESPONSE)
        ;

        $client = $this->authenticateClient();
        $data = $client->getDailyStatsReport($platform, $year, $month);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $data);
    }


    public function provideLoadTasksParams()
    {
        return;
    }

    /**
     * @test
     */
    public function loadTasksWithJson()
    {
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

        $this->expectValidHttpAuthentication()
             ->thenExpectPostRequest("/google/load_tasks/", $payload, self::SAMPLE_JSON_RESPONSE)
        ;

        $sessionData = $this->authenticateClient()
                            ->loadTasks('google', self::VALID_SESSION_ID, $pageSize, $pagesTotal, $queries,
                                $extraParams)
        ;

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    /**
     * @test
     */
    public function getTasksSessionStatus()
    {
        $this->expectValidHttpAuthentication()
             ->thenExpectGetRequest(
                 "/google/session/".self::VALID_SESSION_ID."/",
                 [],
                 self::SAMPLE_JSON_RESPONSE
             )
        ;

        $sessionData = $this->authenticateClient()
                            ->getTasksSessionStatus('google', self::VALID_SESSION_ID)
        ;

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    /**
     * @test
     */
    public function getTasksSessionResults()
    {
        $limit = 1000;
        $offset = 2000;

        $this->expectValidHttpAuthentication()
             ->thenExpectGetRequest(
                 "/google/results/".self::VALID_SESSION_ID."/",
                 [
                     'limit' => $limit,
                     'offset' => $offset,
                 ],
                 self::SAMPLE_JSON_RESPONSE
             )
        ;

        $sessionData = $this->authenticateClient()
                            ->getTasksSessionResults('google', self::VALID_SESSION_ID, $limit, $offset)
        ;

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    private function expectNoHttpAuthentication()
    {
        $this->httpClientMock
            ->expects(self::never())
            ->method('request')
        ;
    }

    private function thenExpectGetRequest(string $path, array $querySubset, array $returnData): self
    {
        $this->thenExpectSingleRequest()
             ->with('GET', self::BASE_URL.$path, new ArraySubset(['query' => $querySubset]))
             ->willReturn(new HttpResponseMock(200, $returnData))
        ;

        return $this;
    }

    private function thenExpectPostRequest(string $path, array $payload, array $returnData)
    {
        $this->thenExpectSingleRequest()
             ->with('POST', self::BASE_URL.$path, new ArraySubset(['json' => $payload]))
             ->willReturn(new HttpResponseMock(200, $returnData))
        ;

        return $this;
    }

    private function expectSingleRequest(): InvocationMocker
    {
        return $this->thenExpectSingleRequest();
    }

    private function thenExpectSingleRequest(): InvocationMocker
    {
        $expectation = $this->httpClientMock
            ->expects(self::at($this->requestCounter))
            ->method('request')
        ;

        $this->requestCounter = $this->requestCounter + 1;

        return $expectation;
    }
}
