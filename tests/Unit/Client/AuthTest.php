<?php

namespace Tests\Unit\Client;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\ArraySubset;
use SeoApi\Client\ApiClient;
use SeoApi\Client\Exception\BadResponseException;
use Tests\Lib\FormPayload;
use Tests\Lib\RequestTesterTrait;
use Tests\Unit\UnitTestCase;

class AuthTest extends UnitTestCase
{
    use RequestTesterTrait;

    private const SAMPLE_JSON_RESPONSE = ['region1', 'region2', ['nestedData' => [1, 2, 3]]];
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

    protected function setUp()
    {
        parent::setUp();
        $this->setupRequestTester();
    }

    /**
     * @test
     */
    public function createdFromCredentials()
    {
        $client = $this->getAuthenticatedClient();

        self::assertInstanceOf(ApiClient::class, $client);
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
     *
     * @param int $code
     */
    public function failsAuthentication(int $code)
    {
        $badUsername = 'foo';
        $badPassword = 'bar';

        $this->expectFailedHttpAuthentication($badUsername, $badPassword, $code);
        $this->expectException(BadResponseException::class);

        ApiClient::fromCredentials($badUsername, $badPassword, self::BASE_URL, $this->httpClientFactory);
    }

    /**
     * @test
     */
    public function createdFromToken()
    {
        $client = ApiClient::fromToken(self::VALID_TOKEN, self::BASE_URL, $this->httpClientFactory);

        self::assertInstanceOf(ApiClient::class, $client);
    }

    public function providePublicMethods()
    {
        return [
            ['getRegions', self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE), 'москва'],
            ['getAggregateStatsReport', self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE), 'google', 'today'],
            ['getDailyStatsReport', self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE), 'google', 2019, 6],
        ];
    }

    /**
     * @test
     * @dataProvider providePublicMethods
     *
     * @param string $method
     * @param Response $response
     * @param array $args
     */
    public function signsEachRequestFromToken(string $method, Response $response, ...$args)
    {
        $tokenClient = ApiClient::fromToken(self::VALID_TOKEN, self::BASE_URL, $this->httpClientFactory);
        $this->expectSignedRequest($response);

        $tokenClient->$method(...$args);
    }

    /**
     * @test
     * @dataProvider providePublicMethods
     *
     * @param string $method
     * @param Response $response
     * @param array $args
     */
    public function signsEachRequestFromCredentials(string $method, Response $response, ...$args)
    {
        $authorizedClient = $this->getAuthenticatedClient();
        $this->expectSignedRequest($response);

        $authorizedClient->$method(...$args);
    }

    private function getAuthenticatedClient(): ApiClient
    {
        $this->expectValidHttpAuthentication(self::USERNAME, self::PASSWORD);

        return ApiClient::fromCredentials(
            self::USERNAME,
            self::PASSWORD,
            self::BASE_URL,
            $this->httpClientFactory
        );
    }

    private function expectFailedHttpAuthentication(string $badUsername, string $badPassword, int $code): void
    {
        $request = self::expectRequest()
                       ->withMethod(self::equalTo('POST'))
                       ->withPath(self::equalTo('/user/obtain_token/'))
                       ->withPayload(new FormPayload([
                           'username' => $badUsername,
                           'password' => $badPassword,
                       ]))
        ;
        $this->expectResponse($request, new Response($code, []));
    }

    protected function expectValidHttpAuthentication(string $username, string $password): void
    {
        $request = self::expectRequest()
                       ->withMethod(Assert::equalTo('POST'))
                       ->withPath(Assert::equalTo('/user/obtain_token/'))
                       ->withPayload(new FormPayload([
                           'username' => $username,
                           'password' => $password,
                       ]))
        ;
        $this->expectResponse($request, self::jsonOkResponse(self::AUTH_USER_RESPONSE));
    }

    private function expectSignedRequest(Response $expectResponse): void
    {
        $authHeaders = new ArraySubset(['Authorization' => ['Token '.self::VALID_TOKEN]]);

        $this->expectResponse(
            self::expectRequest()->withHeaders($authHeaders),
            $expectResponse
        );
    }

}