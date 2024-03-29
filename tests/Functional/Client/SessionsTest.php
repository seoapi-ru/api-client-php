<?php

namespace Tests\Functional\Client;

use SeoApi\Client\Session\QueryBuilder;
use SeoApi\Client\Session\SessionBuilder;
use SeoApi\Client\Session\SessionResult;
use Tests\Functional\FunctionalTestCase;

class SessionsTest extends FunctionalTestCase
{
    private const NEW_SESSION_JSON_SCHEMA = <<<'JSON'
    {
      "$schema": "http://json-schema.org/draft-04/schema#",
      "type": "object",
      "properties": {
        "status": {
          "type": "string"
        },
        "session_id": {
          "type": "number"
        },
        "query_ids": {
          "type": "array"
        }
      },
      "required": [
        "status",
        "session_id",
        "query_ids"
      ]
    }
JSON;

    private const SESSION_STATUS_JSON_SCHEMA = <<<'JSON'
    {
      "$schema": "http://json-schema.org/draft-04/schema#",
      "type": "object",
      "properties": {
        "done": {
          "type": "number"
        },
        "total": {
          "type": "number"
        },
        "finished_at": {
          "type": "number"
        },
        "started_at": {
          "type": "number"
        },
        "progress": {
          "type": "number"
        },
        "status": {
          "type": "string"
        }
      },
      "required": [
        "status",
        "done",
        "total",
        "started_at",
        "finished_at",
        "progress"
      ]
    }
JSON;
    private const SESSION_RESULT_JSON_SCHEMA = <<<'JSON'
    {
      "$schema": "http://json-schema.org/draft-04/schema#",
      "type": "object",
      "properties": {
        "type": "array",
        "items": {
          "type": "object",
          "properties": {
            "count_results": {
              "type": "number"
            },
            "created_at": {
              "type": "date-time"
            },
            "started_at": {
              "type": "number"
            },
            "position": {
              "type": "number"
            },
            "page": {
              "type": "number"
            },
            "url": {
              "type": "string"
            },
            "cached_url": {
              "type": "string"
            },
            "title": {
              "type": "string"
            },
            "snippet": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "status",
        "done",
        "total",
        "started_at",
        "finished_at",
        "progress"
      ]
    }
JSON;
    const SESSION_TIMEOUT = 20;
    const PAGE_SIZE = 10;
    const PAGES_TOTAL = 1;
    const TEST_QUERY_ID = 'query-id-test';
    const SAMPLE_QUERY = 'утраченное время';

    /**
     * @test
     */
    public function runTaskFlow()
    {
        $sessionId = $this->faker->uuid;
        $session = (new SessionBuilder($sessionId, 'google', self::PAGE_SIZE, self::PAGES_TOTAL));
        $session->addQuery(new QueryBuilder(self::SAMPLE_QUERY, self::TEST_QUERY_ID));

        $sessionStarted = $this->client->loadTasks($session);

        self::assertNotEmpty($sessionStarted);
        $this->assertJsonSchemaIsValid($sessionStarted, self::NEW_SESSION_JSON_SCHEMA);

        self::assertSame($sessionId, $sessionStarted['session_id']);
        self::assertSame('OK', $sessionStarted['status']);
        self::assertSame([self::TEST_QUERY_ID], $sessionStarted['query_ids']);

        $this->client->waitForSessionFinish($session, self::SESSION_TIMEOUT, function (array $statusData) {
            $this->assertJsonSchemaIsValid($statusData, self::SESSION_STATUS_JSON_SCHEMA);
        });

        $results = SessionResult::iterateSessionResults($session, $this->client, 10);
        $this->assertJsonSchemaIsValid($results, self::SESSION_RESULT_JSON_SCHEMA);
    }
}