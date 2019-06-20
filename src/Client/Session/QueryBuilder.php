<?php

namespace SeoApi\Client\Session;

use JsonSerializable;

final class QueryBuilder implements JsonSerializable
{
    /** @var string */
    private $query;
    /** @var string */
    private $queryId;
    /** @var int */
    private $pageSize;
    /** @var int */
    private $pagesTotal;
    /** @var int */
    private $regionId;

    public function __construct(string $query, string $queryId = null)
    {
        $this->query = $query;
        $this->queryId = $queryId;
    }

    public static function fromArray($query): self
    {
        $builder = new static($query['query'], $query['query_id'] ?? null);
        if (isset($query['numdoc'], $query['total_pages'])) {
            $builder->paginate($query['numdoc'], $query['total_pages']);
        }
        if (isset($query['region'])) {
            $builder->region($query['region']);
        }

        return $builder;
    }

    public function paginate(int $pageSize, int $pagesTotal): self
    {
        $this->pageSize = $pageSize;
        $this->pagesTotal = $pagesTotal;

        return $this;
    }

    public function region(int $regionId): self
    {
        $this->regionId = $regionId;

        return $this;
    }

    public function toArray(): array
    {
        $data = ['query' => $this->query];
        if (!empty($this->queryId)) {
            $data['query_id'] = $this->queryId;
        }
        if (!empty($this->pageSize)) {
            $data['numdoc'] = $this->pageSize;
        }
        if (!empty($this->pagesTotal)) {
            $data['total_pages'] = $this->pagesTotal;
        }
        if (!empty($this->regionId)) {
            $data['region'] = $this->regionId;
        }

        return $data;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}