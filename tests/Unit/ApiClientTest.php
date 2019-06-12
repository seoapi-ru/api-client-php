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
    public function authenticatesRequestsWithCredentials()
    {
        $this->expectValidHttpAuthentication();
        $this->authenticateClient();
    }

    private function authenticateClient(): ApiClient
    {
        $apiClient = new ApiClient($this->httpClientMock, self::BASE_URL, self::USERNAME, self::PASSWORD);
        $this->resetRequestExpectationsCounter();

        return $apiClient;
    }

    private function expectValidHttpAuthentication(): void
    {
        $this->expectSingleRequest()
            ->with('POST', self::BASE_URL.'/user/obtain_token', new ArraySubset([
                'body' => [
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ],
            ]))
            ->willReturn(new HttpResponseMock(200, self::AUTH_USER_RESPONSE))
        ;
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

        new ApiClient($this->httpClientMock, self::BASE_URL, $badUsername, $badPassword);
    }

    private function expectFailedHttpAuthentication(
        string $baseUrl,
        string $badUsername,
        string $badPassword,
        int $code
    ): void {
        $this->expectSingleRequest()
            ->with('POST', $baseUrl.'/user/obtain_token', new ArraySubset([
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
            ['getRegions', self::SAMPLE_JSON_DATA],
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
        $this->expectValidHttpAuthentication();
        $this->expectSingleRequest()
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

    private function expectSingleRequest(): InvocationMocker
    {
        $expectation = $this->httpClientMock
            ->expects(self::at($this->requestCounter))
            ->method('request')
        ;

        $this->requestCounter = $this->requestCounter + 1;

        return $expectation;
    }

    /**
     * @test
     */
    public function getRegions()
    {
        $queryFilter = 'москва';

        $this->expectValidHttpAuthentication();
        $this->expectSingleRequest()
            ->with('GET', self::BASE_URL.'/google/regions', new ArraySubset([
                'query' => ['q' => $queryFilter],
            ]))
            ->willReturn(new HttpResponseMock(200, self::SAMPLE_JSON_DATA))
        ;

        $client = $this->authenticateClient();
        $regions = $client->getRegions($queryFilter);

        self::assertEquals(self::SAMPLE_JSON_DATA, $regions);
    }

    private function resetRequestExpectationsCounter(): void
    {
        $this->requestCounter = 0;
    }
}
