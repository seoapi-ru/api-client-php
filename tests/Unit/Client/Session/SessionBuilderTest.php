<?php

namespace Tests\Unit\Client\Session;

use SeoApi\Client\Session\QueryBuilder;
use SeoApi\Client\Session\SessionBuilder;
use Tests\Unit\UnitTestCase;
use function file_put_contents;
use function json_encode;

class SessionBuilderTest extends UnitTestCase
{
    private const VALID_SESSION_ID = '07d38bbc-1a97-4f82-acf7-fd0c5766e095';
    private const PAGE_SIZE = 100;
    private const PAGES_TOTAL = 10;
    private const PLATFORM_NAME = 'google';

    private const JSON_FIELDS_REQUIRED = [
        'source' => self::PLATFORM_NAME,
        'session_id' => self::VALID_SESSION_ID,
        'numdoc' => self::PAGE_SIZE,
        'total_pages' => self::PAGES_TOTAL,
    ];

    /** @var SessionBuilder */
    private $baseBuilder;

    protected function setUp()
    {
        parent::setUp();

        $this->baseBuilder = new SessionBuilder(
            self::VALID_SESSION_ID,
            self::PLATFORM_NAME,
            self::PAGE_SIZE,
            self::PAGES_TOTAL
        );
    }

    /**
     * @test
     */
    public function getPlatform()
    {
        self::assertSame(self::PLATFORM_NAME, $this->baseBuilder->getPlatform());
    }

    /**
     * @test
     */
    public function requiresQuery()
    {
        $this->expectException(\LogicException::class);

        $this->baseBuilder->toArray();
    }

    /**
     * @test
     */
    public function addsQuery()
    {
        $query = $this->randomQuery();
        $builder = $this->baseBuilder->addQuery($query);

        $data = $builder->toArray();

        self::assertContainsRequiredFields($data);
        self::assertHasQueries($data);
        self::assertContainsQuery($query, $data, 0);
        self::assertSame($builder->getId(), self::VALID_SESSION_ID);
    }

    /**
     * @test
     */
    public function addsQueriesFromFile()
    {
        $queriesFile = __DIR__.'/session_builder_queries_sample.json';
        $fileQueries = [
            $this->randomQuery(),
            $this->randomQuery(),
        ];
        $written = file_put_contents($queriesFile, json_encode($fileQueries));
        self::assertNotFalse($written, "Error on writing $queriesFile");

        $builder = $this->baseBuilder->addQueryFile($queriesFile);
        $data = $builder->toArray();

        self::assertContainsRequiredFields($data);
        self::assertHasQueries($data);
        foreach ($fileQueries as $i => $query) {
            self::assertContainsQuery($query, $data, $i);
        }
        self::assertSame($builder->getId(), self::VALID_SESSION_ID);
    }

    /**
     * @test
     */
    public function addsDomain()
    {
        $query = $this->randomQuery();
        $builder = $this->baseBuilder->addQuery($query);

        $data = $builder->toArray();
        self::assertArrayNotHasKey('domain', $data);

        $builder->domain('google.ru');
        $data = $builder->toArray();

        self::assertSame('google.ru', $data['domain']);
    }

    /**
     * @test
     */
    public function addsIsMobile()
    {
        $builder = $this->baseBuilder->addQuery($this->randomQuery());

        $data = $builder->toArray();
        self::assertArrayNotHasKey('is_mobile', $data);

        $builder->isMobile(true);
        $data = $builder->toArray();

        self::assertSame(1, $data['is_mobile']);

        $builder->isMobile(false);
        $data = $builder->toArray();

        self::assertSame(0, $data['is_mobile']);
    }

    /**
     * @test
     */
    public function addsRegion()
    {
        $builder = $this->baseBuilder->addQuery($this->randomQuery());

        $data = $builder->toArray();
        self::assertArrayNotHasKey('region', $data);

        $builder->region(666);
        $data = $builder->toArray();

        self::assertSame(666, $data['region']);
    }

    /**
     * @test
     */
    public function addsParams()
    {
        $builder = $this->baseBuilder->addQuery($this->randomQuery());

        $data = $builder->toArray();
        self::assertArrayNotHasKey('params', $data);

        $paramsExpected = ['foo' => 123, 'bar' => 'xyz'];
        $builder->params($paramsExpected);
        $data = $builder->toArray();

        self::assertSame($paramsExpected, $data['params']);
    }

    /**
     * @test
     */
    public function encodesToJson()
    {
        $builder = $this->baseBuilder->addQuery($this->randomQuery());

        self::assertSame(json_encode($builder->toArray()), json_encode($builder));
    }

    private function randomQuery(): QueryBuilder
    {
        $query = (new QueryBuilder($this->faker->sentence(2), $this->faker->uuid))
            ->paginate(
                $this->faker->numberBetween(1, 10),
                $this->faker->numberBetween(11, 100)
            )
            ->region(
                $this->faker->numberBetween(101, 300)
            )
        ;

        return $query;
    }

    private static function assertContainsQuery(QueryBuilder $query, array $builderArray, int $at): void
    {
        self::assertEquals(
            $query->toArray(),
            $builderArray['queries'][$at]
        );
    }

    private static function assertHasQueries(array $builderArray): void
    {
        self::assertArrayHasKey('queries', $builderArray);
    }

    private static function assertContainsRequiredFields(array $builderArray): void
    {
        self::assertArraySubset(self::JSON_FIELDS_REQUIRED, $builderArray);
    }
}