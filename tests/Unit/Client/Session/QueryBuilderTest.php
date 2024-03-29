<?php

namespace Tests\Unit\Client\Session;

use SeoApi\Client\Session\QueryBuilder;
use Tests\Unit\UnitTestCase;
use function json_encode;

class QueryBuilderTest extends UnitTestCase
{
    const QUERY_TEXT = 'грузите апельсины бочками';
    const QUERY_ID = 'any GUID / UUID';
    const PAGE_SIZE = 100;
    const PAGES_COUNT = 12;
    const REGION = 878;
    const SAMPLE_QUERY_DATA = [
        'query' => self::QUERY_TEXT,
        'query_id' => self::QUERY_ID,
        'numdoc' => self::PAGE_SIZE,
        'total_pages' => self::PAGES_COUNT,
        'region' => self::REGION,
    ];

    /**
     * @test
     */
    public function createFromText()
    {
        $query = (new QueryBuilder(self::QUERY_TEXT));

        self::assertSame(
            ['query' => self::QUERY_TEXT],
            $query->toArray()
        );
    }

    /**
     * @test
     */
    public function createWithID()
    {
        $query = (new QueryBuilder(self::QUERY_TEXT, self::QUERY_ID));

        self::assertSame(
            [
                'query' => self::QUERY_TEXT,
                'query_id' => self::QUERY_ID,
            ],
            $query->toArray()
        );
    }

    /**
     * @test
     */
    public function addsRegion()
    {
        $query = (new QueryBuilder(self::QUERY_TEXT, self::QUERY_ID));

        $query->region(self::REGION);

        self::assertSame(
            [
                'query' => self::QUERY_TEXT,
                'query_id' => self::QUERY_ID,
                'region' => self::REGION,
            ],
            $query->toArray()
        );
    }

    /**
     * @test
     */
    public function addsPagination()
    {
        $query = (new QueryBuilder(self::QUERY_TEXT, self::QUERY_ID));

        $query->paginate(self::PAGE_SIZE, self::PAGES_COUNT);

        self::assertSame(
            [
                'query' => self::QUERY_TEXT,
                'query_id' => self::QUERY_ID,
                'numdoc' => self::PAGE_SIZE,
                'total_pages' => self::PAGES_COUNT,
            ],
            $query->toArray()
        );
    }

    /**
     * @test
     */
    public function createFromArray()
    {
        $query = QueryBuilder::fromArray(self::SAMPLE_QUERY_DATA);

        self::assertSame(self::SAMPLE_QUERY_DATA, $query->toArray());
    }

    /**
     * @test
     */
    public function jsonSerializable()
    {
        $query = QueryBuilder::fromArray(self::SAMPLE_QUERY_DATA);
        $dataJson = json_encode(self::SAMPLE_QUERY_DATA);

        self::assertNotFalse($dataJson);
        self::assertSame($dataJson, json_encode($query));
    }
}
