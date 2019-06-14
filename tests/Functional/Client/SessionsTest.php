<?php

namespace Tests\Functional\Client;

use Tests\Functional\FunctionalTestCase;
use function random_bytes;
use function sha1;
use function usleep;

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
        "total": {
          "type": "number",
          "minimum": 0
        },
        "results": {
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
    const SESSION_TIMEOUT = 15;

    /**
     * @test
     */
    public function runTaskFlow()
    {
        $sessionId = sha1(random_bytes(8));
        $queryId = sha1(random_bytes(8));
        $sessionStarted = $this->client->loadTasks('google', $sessionId, 10, 1, [
            [
                'query' => 'test',
                'query_id' => $queryId,
            ],
        ]);

        self::assertNotEmpty($sessionStarted);
        $this->assertJsonSchemaIsValid($sessionStarted, self::NEW_SESSION_JSON_SCHEMA);

        self::assertSame($sessionId, $sessionStarted['session_id']);
        self::assertSame('OK', $sessionStarted['status']);
        self::assertSame([$queryId], $sessionStarted['query_ids']);

        $timePassed = 0;
        $results = null;

        while ($timePassed < self::SESSION_TIMEOUT) {
            $timePassed += 0.1;
            usleep(0.1 * 1000 * 1000);
            $status = $this->client->getTasksSessionStatus('google', $sessionId);
            $this->assertJsonSchemaIsValid($status, self::SESSION_STATUS_JSON_SCHEMA);
            if ($status['status'] === 'finished') {
                $results = $this->client->getTasksSessionResults('google', $sessionId, 10);
                break;
            }
        }

        self::assertNotNull($results);
        $this->assertJsonSchemaIsValid($results, self::SESSION_RESULT_JSON_SCHEMA);
    }
}