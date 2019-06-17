<?php

namespace Tests\Functional\Client;

use SeoApi\Client\ApiClient;
use Tests\Functional\FunctionalTestCase;

class StatReportTest extends FunctionalTestCase
{
    private const AGGREGATE_JSON_SCHEMA = <<<'JSON'
    {
      "$schema": "http://json-schema.org/draft-04/schema#",
      "type": "object",
        "properties": {
          "query_count": {
            "type": "number"
          },
          "session_count": {
            "type": "number"
          }
        },            
      "required": ["query_count", "session_count"]
    }
JSON;

    private const DAILY_JSON_SCHEMA = <<<'JSON'
    {
      "$schema": "http://json-schema.org/draft-04/schema#",
      "type": "object",
        "properties": {
          "query_count": {
            "type": "number"
          },
          "session_count": {
            "type": "number"
          }
        },            
      "required": ["query_count", "session_count"]
    }
JSON;

    private const SUPPORTED_DAILY_STAT_PLATFORMS = ['google'/*, 'yandex', 'wordstat'*/];

    public function provideAggregateStatRequests(): iterable
    {
        foreach (ApiClient::SEARCH_PLATFORMS as $platform) {
            foreach (ApiClient::STATS_PERIODS as $period) {
                yield [$platform, $period];
            }
        }
    }

    /**
     * @test
     * @dataProvider provideAggregateStatRequests
     *
     * @param string $platform
     * @param string $period
     */
    public function getAggregateStatsReportMatchesSchema(string $platform, string $period)
    {
        self::markTestSkipped('Waits for confirmation of method deprecation');

        $regions = $this->client->getAggregateStatsReport($platform, $period);

        self::assertNotEmpty($regions);
        $this->assertJsonSchemaIsValid($regions, self::AGGREGATE_JSON_SCHEMA);
    }

    public function provideDailyStatsRequests(): iterable
    {
        $year = 2019;
        $month = 1;
        foreach (self::SUPPORTED_DAILY_STAT_PLATFORMS as $platform) {
            yield [$platform, $year, $month];
        }
    }

    /**
     * @test
     * @dataProvider provideDailyStatsRequests
     *
     * @param string $platform
     * @param int $year
     * @param int $month
     */
    public function getDailyStatsReportMatchesSchema(string $platform, int $year, int $month)
    {
        $regions = $this->client->getDailyStatsReport($platform, $year, $month);

        self::assertNotEmpty($regions);
        $this->assertJsonSchemaIsValid($regions, self::DAILY_JSON_SCHEMA);
    }
}