<?php

namespace Tests\Functional\Client;

use Tests\Functional\FunctionalTestCase;

class RegionsTest extends FunctionalTestCase
{
    private const JSON_SCHEMA = <<<'JSON'
    {
        "$schema": "http://json-schema.org/draft-04/schema#",
        "type": "array",
        "items": {
          "type":  "object",
          "properties": {
            "region_id": {
              "type": "number",
              "minimum": 0
            },
            "name_ru": {
              "type": "boolean"
            },
            "name": {
              "type": "string"
            }            
          },
          "required": ["region_id", "name"]
        }
    }
JSON;

    /**
     * @test
     */
    public function returnsValidSchema()
    {
        $regions = $this->client->getRegions('москва');

        self::assertNotEmpty($regions);
        $this->assertJsonSchemaIsValid($regions, self::JSON_SCHEMA);
    }
}