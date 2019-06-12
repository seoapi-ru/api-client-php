<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

class FunctionalTestCase extends TestCase
{
    private const ENV_FILE_PATH = __DIR__.'/../.env';

    public static function setUpBeforeClass()
    {
        // add local vars
        $envFilePath = self::ENV_FILE_PATH;
        if (file_exists($envFilePath)) {
            (new \Symfony\Component\Dotenv\Dotenv())->overload($envFilePath);
        }
    }
}