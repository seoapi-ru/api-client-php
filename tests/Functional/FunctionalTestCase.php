<?php

namespace Tests\Functional;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use SeoApi\Client\ApiClient;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

class FunctionalTestCase extends TestCase
{
    private const ENV_FILE_PATH = __DIR__.'/../.env';
    /** @var Validator */
    protected $jsonSchemaValidator;
    /** @var ApiClient */
    protected $client;

    public static function setUpBeforeClass()
    {
        // add local vars
        $envFilePath = self::ENV_FILE_PATH;
        if (file_exists($envFilePath)) {
            (new Dotenv())->overload($envFilePath);
        }
    }

    protected function setUp()
    {
        $this->client = ApiClient::fromToken(
            getenv('SEOAPI_CLIENT_TOKEN'),
            getenv('SEOAPI_CLIENT_BASEURL'),
            HttpClient::create(['base_uri' => getenv('SEOAPI_CLIENT_BASEURL')])
        );

        $this->jsonSchemaValidator = new Validator();
    }


    protected function assertJsonSchemaIsValid($data, string $schemaJson): void
    {
        $schemaDecoded = json_decode($schemaJson, false);

        try {
            $this->jsonSchemaValidator
                ->validate(
                    $data,
                    $schemaDecoded,
                    Constraint::CHECK_MODE_EXCEPTIONS & Constraint::CHECK_MODE_TYPE_CAST
                );
        } catch (\Throwable $e) {
            self::fail("JSON schema is not passed validation: ".$e->getMessage()."\n"
                ."JSON data:\n".var_export($data, true)."\n");
        }
    }
}