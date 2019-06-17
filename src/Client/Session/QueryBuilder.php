<?php

namespace SeoApi\Client\Session;

final class QueryBuilder
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
}