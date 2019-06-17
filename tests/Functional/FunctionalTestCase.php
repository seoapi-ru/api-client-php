<?php

namespace Tests\Functional;

use Faker\Factory;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use SeoApi\Client\ApiClient;
use SeoApi\Client\HttpClientFactory;
use Symfony\Component\Dotenv\Dotenv;

class FunctionalTestCase extends TestCase
{
    private const ENV_FILE_PATH = __DIR__.'/../.env';
    /** @var Validator */
    protected $jsonSchemaValidator;
    /** @var ApiClient */
    protected $client;
    /** @var \Faker\Generator */
    protected $faker;

    public static function setUpBeforeClass()
    {
        self::loadExtraEnvironment();
    }

    private static function loadExtraEnvironment(): void
    {
        $envFilePath = self::ENV_FILE_PATH;
        if (file_exists($envFilePath)) {
            (new Dotenv())->overload($envFilePath);
        }
    }

    protected function setUp()
    {
        $this->faker = Factory::create('ru');

        $this->client = ApiClient::fromToken(
            getenv('SEOAPI_CLIENT_TOKEN'),
            getenv('SEOAPI_CLIENT_BASEURL'),
            new HttpClientFactory()
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