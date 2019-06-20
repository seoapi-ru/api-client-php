<?php

namespace SeoApi\Client\Session;

use InvalidArgumentException;
use JsonSerializable;
use function file_get_contents;
use function is_array;
use function is_readable;
use function json_decode;

final class SessionBuilder implements JsonSerializable
{
    /** @var string */
    private $platform;
    /** @var string */
    private $sessionId;
    /** @var QueryBuilder[] */
    private $queries = [];
    /** @var int */
    private $pageSize;
    /** @var int */
    private $pagesTotal;
    /** @var string */
    private $domain;
    /** @var bool */
    private $isMobile;
    /** @var int */
    private $regionId;
    /** @var array */
    private $params;

    public function __construct(string $sessionId, string $platform, int $pageSize, int $pagesTotal)
    {
        $this->sessionId = $sessionId;
        $this->platform = $platform;
        $this->pageSize = $pageSize;
        $this->pagesTotal = $pagesTotal;
    }

    public function toArray(): array
    {
        if (empty($this->queries)) {
            throw new \LogicException("Add at least one query with ::addQuery()");
        }

        $data = [
            'source' => $this->platform,
            'session_id' => $this->sessionId,
            'numdoc' => $this->pageSize,
            'total_pages' => $this->pagesTotal,
            'queries' => [],
        ];

        foreach ($this->queries as $query) {
            $data['queries'][] = $query->toArray();
        }

        if (!empty($this->domain)) {
            $data['domain'] = $this->domain;
        }
        if (null !== $this->isMobile) {
            $data['is_mobile'] = (int)$this->isMobile;
        }
        if (!empty($this->regionId)) {
            $data['region'] = $this->regionId;
        }
        if (!empty($this->params)) {
            $data['params'] = $this->params;
        }

        return $data;
    }

    public function addQuery(QueryBuilder $query): self
    {
        $this->queries[] = $query;

        return $this;
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function isMobile(bool $isMobile): self
    {
        $this->isMobile = $isMobile;

        return $this;
    }

    public function region(int $regionId): self
    {
        $this->regionId = $regionId;

        return $this;
    }

    public function params(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getId(): string
    {
        return $this->sessionId;
    }

    public function addQueryFile(string $queriesFile): self
    {
        if (!is_readable($queriesFile)) {
            throw new InvalidArgumentException("File is not readable: $queriesFile");
        }
        $data = json_decode(file_get_contents($queriesFile), true);
        if (!is_array($data)) {
            throw new InvalidArgumentException("JSON is not decoded as array in $queriesFile");
        }
        foreach ($data as $query) {
            $this->addQuery(QueryBuilder::fromArray($query));
        }

        return $this;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}