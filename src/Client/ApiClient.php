<?php

namespace SeoApi\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException as BadGuzzleResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use SeoApi\Client\Exception\AuthException;
use SeoApi\Client\Exception\BadResponseException;
use SeoApi\Client\Exception\TransportException;
use SeoApi\Client\Session\SessionBuilder;
use function is_array;

final class ApiClient
{
    public const STATS_PERIODS = ['all', 'month', 'today'];
    public const SEARCH_PLATFORMS = ['google', 'yandex', 'wordstat'];
    /** @var ClientInterface */
    private $httpClient;
    /** @var string */
    private $baseUrl;
    /** @var string|null */
    private $token;

    public function __construct(HttpClientFactory $httpClientFactory, string $baseUrl)
    {
        $this->httpClient = $httpClientFactory->create($baseUrl);
        $this->baseUrl = $baseUrl;
    }

    private function authenticate(string $username, string $password): void
    {
        try {
            $response = $this->sendPostApiRequest('/user/obtain_token/', [
                'username' => $username,
                'password' => $password,
            ]);
            $unserializedResponse = $this->unserializeResponse($response);

            if (empty($unserializedResponse['user']['token'])) {
                throw new BadResponseException("No token in auth response");
            }

            $this->token = $unserializedResponse['user']['token'];
        } catch (BadResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AuthException('Unexpected error: '.$e->getMessage());
        }
    }

    public static function fromToken(string $token, string $baseUrl, HttpClientFactory $httpClientFactory): self
    {
        $client = new static($httpClientFactory, $baseUrl);
        $client->token = $token;

        return $client;
    }

    public static function fromCredentials(
        string $username,
        string $password,
        string $baseUrl,
        HttpClientFactory $httpClientFactory
    ): self {
        $client = new static($httpClientFactory, $baseUrl);
        $client->authenticate($username, $password);

        return $client;
    }

    public function getRegions(string $filter): array
    {
        $response = $this->sendGetApiRequest('/google/regions/', [
            'q' => $filter,
        ]);

        return $this->unserializeResponse($response);
    }

    /**
     * @param string $platform
     * @param string $period
     * @return array
     * @deprecated Used anywhere?
     *
     */
    public function getAggregateStatsReport(string $platform, string $period): array
    {
        $response = $this->sendGetApiRequest("/{$platform}/user/report/", [
            'report_type' => $period,
        ]);

        return $this->unserializeResponse($response);
    }

    public function getDailyStatsReport(string $platform, int $year, int $month): array
    {
        $response = $this->sendGetApiRequest("/{$platform}/user/report/daily/", [
            'year' => $year,
            'month' => $month,
        ]);

        return $this->unserializeResponse($response);
    }

    public function loadTasks(string $platform, SessionBuilder $session): array
    {
        $response = $this->sendJsonPostApiRequest("/{$platform}/load_tasks/", $session->toArray());

        return $this->unserializeResponse($response);
    }

    public function getTasksSessionStatus(string $platform, string $sessionId): array
    {
        $response = $this->sendGetApiRequest("/{$platform}/session/{$sessionId}/", []);

        return $this->unserializeResponse($response);
    }

    public function getTasksSessionResults(string $platform, string $sessionId, $limit, $offset = 0): array
    {
        $response = $this->sendGetApiRequest("/{$platform}/results/{$sessionId}/", [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $this->unserializeResponse($response);
    }

    private function sendPostApiRequest(string $path, array $postBody): Response
    {
        try {
            $response = $this->httpClient->post($path, [
                RequestOptions::FORM_PARAMS => $postBody,
                RequestOptions::HEADERS => $this->buildHeaders(),
            ]);

            return $response;
        } catch (BadGuzzleResponseException $e) {
            throw new BadResponseException(
                sprintf("Bad HTTP response (%d)", $e->getCode()),
                $e->getCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new TransportException('POST exception: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    private function sendJsonPostApiRequest(string $path, array $payload): Response
    {
        try {
            $response = $this->httpClient->post($path, [
                RequestOptions::JSON => $payload,
                RequestOptions::HEADERS => $this->buildHeaders(),
            ]);


            return $response;
        } catch (BadGuzzleResponseException $e) {
            throw new BadResponseException(
                sprintf("Bad HTTP response (%d)", $e->getCode()),
                $e->getCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new TransportException('POST exception: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    private function sendGetApiRequest(string $path, array $query): Response
    {
        try {
            $response = $this->httpClient->get($this->baseUrl.$path, [
                RequestOptions::QUERY => $query,
                RequestOptions::HEADERS => $this->buildHeaders(),
            ]);

            return $response;
        } catch (BadGuzzleResponseException $e) {
            throw new BadResponseException(
                sprintf("Bad HTTP response (%d)", $e->getCode()),
                $e->getCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new TransportException('GET exception: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    private function buildHeaders(): array
    {
        $headers = [];
        if (!empty($this->token)) {
            $headers['Authorization'] = 'Token '.$this->token;
        }

        return $headers;
    }

    private function unserializeResponse(Response $response): array
    {
        $content = $response->getBody()->getContents();
        $jsonDecoded = \json_decode($content, true);
        if (!is_array($jsonDecoded)) {
            throw new BadResponseException('Can\'t decode JSON: '.$content);
        }

        return $jsonDecoded;
    }
}