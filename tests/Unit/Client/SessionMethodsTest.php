<?php

namespace Tests\Unit\Client;

use PHPUnit\Framework\Exception as PhpUnitException;
use SeoApi\Client\ApiClient;
use SeoApi\Client\Exception\TimeoutExceededError;
use SeoApi\Client\Session\QueryBuilder;
use SeoApi\Client\Session\SessionBuilder;
use Tests\Lib\JsonPayload;
use Tests\Lib\RequestTesterTrait;
use Tests\Unit\UnitTestCase;
use function get_class;
use function time;

class SessionMethodsTest extends UnitTestCase
{
    use RequestTesterTrait;

    private const BASE_URL = 'https://testhost';
    private const SAMPLE_JSON_RESPONSE = ['region1', 'region2', ['nestedData' => [1, 2, 3]]];
    private const VALID_SESSION_ID = '07d38bbc-1a97-4f82-acf7-fd0c5766e095';
    private const AUTH_TOKEN = 'test_token';
    private const PLATFORM = 'google';
    private const SESSION_TIMEOUT = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRequestTester();
    }

    /**
     * @test
     */
    public function loadTasksWithJson()
    {
        $client = $this->getAuthenticatedClient();
        $platform = self::PLATFORM;
        $session = $this->sampleSession($platform);

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('POST'))
                       ->withPath(self::equalTo("/{$platform}/load_tasks/"))
                       ->withPayload(new JsonPayload($session->toArray()))
        ;

        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));

        $sessionData = $client->loadTasks($session);

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

        $sessionData = $client->getTasksSessionStatus(self::PLATFORM, self::VALID_SESSION_ID);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    /**
     * @test
     */
    public function getTasksSessionResults()
    {
        $client = $this->getAuthenticatedClient();

        $path = sprintf("/%s/results/%s/", self::PLATFORM, self::VALID_SESSION_ID);
        $limit = 1000;
        $offset = 2000;
        $query = [
            'limit' => $limit,
            'offset' => $offset,
            'session-id' => self::VALID_SESSION_ID,
        ];

        $request = self::expectRequest()
                       ->withMethod(self::equalTo('GET'))
                       ->withPath(self::equalTo($path))
                       ->withQuery(self::equalTo($query))
        ;

        $this->expectResponse($request, self::jsonOkResponse(self::SAMPLE_JSON_RESPONSE));
        $sessionData = $client->getTasksSessionResults(self::PLATFORM, self::VALID_SESSION_ID, $limit, $offset);

        self::assertSame(self::SAMPLE_JSON_RESPONSE, $sessionData);
    }

    /**
     * @test
     */
    public function waitForSessionFinishSuccess()
    {
        $session = $this->sampleSession(self::PLATFORM);

        $client = $this->getAuthenticatedClient();
        $path = sprintf("/%s/session/%s/", self::PLATFORM, self::VALID_SESSION_ID);

        $pingRequest = $this->expectRequest()
                            ->withMethod(self::equalTo('GET'))
                            ->withPath(self::equalTo($path))
        ;
        $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));
        $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));
        $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'finished']));

        $pingStartedTime = time();
        try {
            $client->waitForSessionFinish($session, self::SESSION_TIMEOUT,
                function ($statusResponse) use ($pingRequest) {
                self::assertNotEquals('finished', $statusResponse['status']);
                });
        } catch (PhpUnitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::fail(get_class($e).": ".$e->getMessage());
        }

        self::assertWaitNotExceededTimeout($pingStartedTime);
    }

    /**
     * @test
     */
    public function waitForSessionFinishTimeoutReached()
    {
        $session = $this->sampleSession(self::PLATFORM);
        $client = $this->getAuthenticatedClient();

        $path = sprintf("/%s/session/%s/", self::PLATFORM, self::VALID_SESSION_ID);
        $pingRequest = $this->expectRequest()
                            ->withMethod(self::equalTo('GET'))
                            ->withPath(self::equalTo($path))
        ;

        $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));

        $timeoutThrown = false;
        $pingStartedTime = time();
        try {
            $client->waitForSessionFinish($session, self::SESSION_TIMEOUT,
                function ($statusResponse) use ($pingRequest) {
                // check waiting step
                self::assertNotEquals('finished', $statusResponse['status']);
                // never return finished status
                $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));
                });
        } catch (TimeoutExceededError $e) {
            $timeoutThrown = true;
        } catch (PhpUnitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::fail($e->getMessage());
        }

        self::assertWaitNotExceededTimeout($pingStartedTime);
        self::assertTrue($timeoutThrown, TimeoutExceededError::class.' should be thrown');
    }


    private function getAuthenticatedClient(): ApiClient
    {
        return ApiClient::fromToken(self::AUTH_TOKEN, self::BASE_URL, $this->httpClientFactory);
    }

    private function sampleSession(string $platform): SessionBuilder
    {
        $session = new SessionBuilder(
            self::VALID_SESSION_ID,
            $platform,
            $this->faker->numberBetween(10, 50),
            $this->faker->numberBetween(1, 5)
        );
        $session = $session->addQuery(new QueryBuilder('test'));

        return $session;
    }

    private static function assertWaitNotExceededTimeout(int $pingStartedTime): void
    {
        self::assertLessThanOrEqual(
            self::SESSION_TIMEOUT + 1,
            time() - $pingStartedTime,
            'Session wait should be less than timeout, rounded to +1 second up'
        );
    }
}