<?php

namespace SeoApi\Client\Session;

use SeoApi\Client\ApiClient;
use SeoApi\Client\Exception\BadResponseException;
use function count;

final class SessionResult
{
    /** @var SessionBuilder */
    private $session;
    /** @var ApiClient */
    private $client;
    /** @var bool */
    private $resultsLeft = true;
    /** @var int */
    private $totalCount;

    private function __construct(SessionBuilder $session, ApiClient $client)
    {
        $this->session = $session;
        $this->client = $client;
    }

    public static function iterateSessionResults(SessionBuilder $session, ApiClient $client, int $pageSize)
    {
        return (new static($session, $client))->iterate($pageSize);
    }

    private function iterate(int $pageSize): iterable
    {
        $offset = 0;
        $dataFetchedCount = 0;

        do {
            $results = $this->loadResultsPage($pageSize, $offset);
            $totalCount = $this->extractTotalCount($results);
            if ($totalCount === 0) {
                break;
            }
            $pageResultsCount = count($results['results']);
            $dataFetchedCount += $pageResultsCount;

            yield $results['results'];

            $this->resultsLeft = ($pageResultsCount > 0) && ($this->totalCount > $dataFetchedCount);
            $offset += $pageSize;
        } while ($this->resultsLeft);
    }

    private function loadResultsPage(int $pageSize, int $offset): array
    {
        $results = $this->client->getTasksSessionResults(
            $this->session->getPlatform(),
            $this->session->getId(),
            $pageSize,
            $offset
        );

        return $results;
    }

    private function extractTotalCount(array $results): int
    {
        if ($this->totalCount === null) {
            if (!isset($results['total'])) {
                throw new BadResponseException("No 'totals' field");
            }
            $this->totalCount = (int)$results['total'];
        }

        return $this->totalCount;
    }

}