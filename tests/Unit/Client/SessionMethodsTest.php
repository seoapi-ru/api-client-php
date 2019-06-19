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
        $platform = 'google';
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

    /**
     * @test
     */
    public function waitForSessionFinishSuccess()
    {
        $platform = 'google';
        $session = $this->sampleSession($platform);

        $client = $this->getAuthenticatedClient();
        $pingRequest = $this->expectRequest()
                            ->withMethod(self::equalTo('GET'))
                            ->withPath(self::equalTo("/{$platform}/session/".self::VALID_SESSION_ID."/"))
        ;
        $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));

        $sessionTimeout = 1;
        $pingStarted = time();
        try {
            $ticker = $client->waitForSessionFinish($session, $sessionTimeout);
            foreach ($ticker as $statusResponse) {
                // check waiting step
                self::assertNotEquals('finished', $statusResponse['status']);
                // then set finish response which should stop the loop
                $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'finished']));
            }
        } catch (PhpUnitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::fail(get_class($e).": ".$e->getMessage());
        }

        self::assertLessThanOrEqual(
            $sessionTimeout + 1,
            time() - $pingStarted,
            'Session wait should be less than timeout, rounded to +1 second up'
        );
    }

    /**
     * @test
     */
    public function waitForSessionFinishTimeoutReached()
    {
        $platform = 'google';
        $session = $this->sampleSession($platform);

        $client = $this->getAuthenticatedClient();
        $pingRequest = $this->expectRequest()
                            ->withMethod(self::equalTo('GET'))
                            ->withPath(self::equalTo("/{$platform}/session/".self::VALID_SESSION_ID."/"))
        ;
        $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));

        $timeoutThrown = false;
        $sessionTimeout = 1;
        $pingStarted = time();
        try {
            $ticker = $client->waitForSessionFinish($session, $sessionTimeout);
            foreach ($ticker as $statusResponse) {
                // check waiting step
                self::assertNotEquals('finished', $statusResponse['status']);
                // never return finished status
                $this->expectResponse($pingRequest, self::jsonOkResponse(['status' => 'unfinished?']));
            }
        } catch (TimeoutExceededError $e) {
            $timeoutThrown = true;
        } catch (PhpUnitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::fail($e->getMessage());
        }

        self::assertLessThanOrEqual(
            $sessionTimeout + 1,
            time() - $pingStarted,
            'Session wait should be less than timeout, rounded to +1 second up'
        );
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
}