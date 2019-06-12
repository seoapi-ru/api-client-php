<?php

namespace Tests\Functional\Client;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use SeoApi\Client\ApiClient;
use Symfony\Component\HttpClient\HttpClient;
use Tests\Functional\FunctionalTestCase;
use function getenv;
use function json_decode;
use function var_export;

class RegionsTest extends FunctionalTestCase
{
    private const JSON_SCHEMA = <<<JSON
    {
        "type": "array",
        "items": {
          "type":  "object",
          "properties": {
            "region_id": {
              "type": "integer",
              "minimum": 0
            },
            "name_ru": {
              "type": "string"
            },
            "name": {
              "type": "string"
            }            
          },
          "required": ["region_id", "name"]
        }
    }
JSON;

    /** @var ApiClient */
    private $client;
    /** @var Validator */
    private $jsonSchemaValidator;

    protected function setUp()
    {
        $this->client = ApiClient::fromToken(
            getenv('SEOAPI_CLIENT_TOKEN'),
            getenv('SEOAPI_CLIENT_BASEURL'),
            HttpClient::create(['base_uri' => getenv('SEOAPI_CLIENT_BASEURL')])
        );

        $this->jsonSchemaValidator = new Validator();
    }

    /**
     * @test
     */
    public function returnsValidSchema()
    {
        $regions = $this->client->getRegions();

        self::assertNotEmpty($regions);
        $this->assertJsonSchemaIsValid($regions, self::JSON_SCHEMA);
    }

    private function assertJsonSchemaIsValid($data, string $schemaJson): void
    {
        $schemaDecoded = json_decode($schemaJson, false);

        try {
            $this->jsonSchemaValidator->validate($regions, $schemaDecoded, Constraint::CHECK_MODE_EXCEPTIONS);
        } catch (\Throwable $e) {
            self::fail("JSON schema is not passed validation:\n".var_export($data, true));
        }
    }
}