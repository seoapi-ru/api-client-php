<?php

namespace Tests\Unit\Client;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use SeoApi\Client\HttpClientFactory;
use Tests\Unit\UnitTestCase;

class HttpClientFactoryTest extends UnitTestCase
{
    /**
     * @test
     */
    public function createsDefaultHttpClient()
    {
        $factory = new HttpClientFactory();

        $httpClient = $factory->create('http://localhost');

        self::assertSame('http://localhost', (string)$httpClient->getConfig('base_uri'));
    }

    /**
     * @test
     */
    public function createsWithCustomHandlerStack()
    {
        $container = [];
        $handler = Middleware::history($container);
        $handlerStack = HandlerStack::create($handler);

        $factory = new HttpClientFactory($handlerStack);

        $httpClient = $factory->create('http://localhost');

        self::assertSame($handlerStack, $httpClient->getConfig('handler'));
    }
}