<?php

namespace SeoApi\Client;

use SeoApi\Client\Exception\AuthException;
use SeoApi\Client\Exception\BadResponseException;
use SeoApi\Client\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function array_merge;
use function is_array;

final class ApiClient
{
    public const STATS_PERIODS = ['all', 'month', 'today'];
    public const SEARCH_PLATFORMS = ['google', 'yandex', 'wordstat'];
    /** @var HttpClientInterface */
    private $httpClient;
    /** @var string */
    private $baseUrl;
    /** @var string|null */
    private $token;

    public function __construct(HttpClientInterface $httpClient, string $baseUrl)
    {
        $this->httpClient = $httpClient;
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

    public static function fromToken(string $token, string $baseUrl, HttpClientInterface $httpClient): self
    {
        $client = new static($httpClient, $baseUrl);
        $client->token = $token;

        return $client;
    }

    public static function fromCredentials(
        string $username,
        string $password,
        string $baseUrl,
        HttpClientInterface $httpClient
    ): self {
        $client = new static($httpClient, $baseUrl);
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

    public function loadTasks(
        string $platform,
        string $sessionId,
        int $pageSize,
        int $pagesTotal,
        array $queries,
        array $extraParams
    ): array {
        $params = [
            'source' => $platform,
            'session_id' => $sessionId,
            'numdoc' => $pageSize,
            'total_pages' => $pagesTotal,
            'queries' => $queries,
        ];

        $response = $this->sendJsonPostApiRequest("/{$platform}/load_tasks/", array_merge($params, $extraParams));

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

    private function sendPostApiRequest(string $path, array $postBody): ResponseInterface
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.$path, [
                'body' => $postBody,
                'headers' => $this->buildHeaders(),
            ]);

            $this->checkResponseCode($response);

            return $response;
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('POST exception: '.$e->getMessage(), 0, $e);
        }
    }

    private function sendJsonPostApiRequest(string $path, array $payload): ResponseInterface
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.$path, [
                'json' => $payload,
                'headers' => $this->buildHeaders(),
            ]);

            $this->checkResponseCode($response);

            return $response;
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('POST exception: '.$e->getMessage(), 0, $e);
        }
    }

    private function sendGetApiRequest(string $path, array $query): ResponseInterface
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.$path, [
                'query' => $query,
                'headers' => $this->buildHeaders(),
            ]);
            $this->checkResponseCode($response);

            return $response;
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('GET exception: '.$e->getMessage(), 0, $e);
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

    private function unserializeResponse(ResponseInterface $response): array
    {
        $content = $response->getContent(false);
        $jsonDecoded = \json_decode($content, true);
        if (!is_array($jsonDecoded)) {
            throw new BadResponseException('Can\'t decode JSON: '.$content);
        }

        return $jsonDecoded;
    }

    private function checkResponseCode(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        if (\in_array($statusCode, \range(400, 599), true)) {
            throw new BadResponseException(
                sprintf("Bad response (%d): %s", $statusCode, $response->getContent(false))
            );
        }
    }
}