<?php

namespace Tests\Unit\Client;

use PHPUnit\Framework\Constraint\ArraySubset;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SeoApi\Client\ApiClient;
use SeoApi\Client\Exception\BadResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Lib\HttpResponseMock;

class ApiClientTest extends TestCase
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
    private const SAMPLE_JSON_DATA = ['region1', 'region2', ['nestedData' => [1, 2, 3]]];
    const SEARCH_PLATFORMS = ['google', 'yandex', 'wordstat'];
    const STATS_PERIODS = ['all', 'month', 'today'];

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
        $this->authenticateClient();
    }

    public function createdFromToken()
    {
        $this->expectNoHttpAuthentication();
        $client = ApiClient::fromToken(self::VALID_TOKEN, self::BASE_URL, $this->httpClientMock);
    }

    private function authenticateClient(): ApiClient
    {
        $apiClient = ApiClient::fromCredentials(self::USERNAME, self::PASSWORD, self::BASE_URL, $this->httpClientMock);
        $this->resetRequestExpectationsCounter();

        return $apiClient;
    }

    private function expectValidHttpAuthentication(): self
    {
        $this->thenExpectSingleRequest()
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
            ['getRegions', self::SAMPLE_JSON_DATA, 'москва'],
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
            ->thenExpectGetRequest('/google/regions/', ['q' => $queryFilter], self::SAMPLE_JSON_DATA)
        ;

        $client = $this->authenticateClient();
        $regions = $client->getRegions($queryFilter);

        self::assertEquals(self::SAMPLE_JSON_DATA, $regions);
    }

    public function provideStatsRequests()
    {
        foreach (self::SEARCH_PLATFORMS as $platform) {
            foreach (self::STATS_PERIODS as $period) {
                yield [
                    $period,
                    $platform,
                    "/{$platform}/user/report/",
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
    public function getAggregateStatsReport(string $period, string $platform, string $expectPath, array $expectQuery)
    {
        $this->expectValidHttpAuthentication()
            ->thenExpectGetRequest($expectPath, $expectQuery, self::SAMPLE_JSON_DATA)
        ;

        $client = $this->authenticateClient();
        $data = $client->getAggregateStatsReport($platform, $period);

        self::assertSame(self::SAMPLE_JSON_DATA, $data);
    }

    public function provideDailyStatsRequests()
    {
        $year = 2019;
        $month = 12;
        foreach (self::SEARCH_PLATFORMS as $platform) {
            yield [
                $platform,
                $year,
                $month,
                "/{$platform}/user/report/daily/",
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
    public function getDailyStatsReport(string $platform, int $year, int $month, string $expectPath, array $expectQuery)
    {
        $this->expectValidHttpAuthentication()
            ->thenExpectGetRequest($expectPath, $expectQuery, self::SAMPLE_JSON_DATA)
        ;

        $client = $this->authenticateClient();
        $data = $client->getDailyStatsReport($platform, $year, $month);

        self::assertSame(self::SAMPLE_JSON_DATA, $data);
    }


    private function resetRequestExpectationsCounter(): void
    {
        $this->requestCounter = 0;
    }

    private function expectNoHttpAuthentication()
    {
        $this->httpClientMock
            ->expects(self::never())
            ->method('request')
        ;
    }

    private function thenExpectGetRequest(string $expectPath, array $expectQuerySubset, array $returnData): self
    {
        $this->thenExpectSingleRequest()
            ->with('GET', self::BASE_URL.$expectPath, new ArraySubset(['query' => $expectQuerySubset]))
            ->willReturn(new HttpResponseMock(200, $returnData))
        ;

        return $this;
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
