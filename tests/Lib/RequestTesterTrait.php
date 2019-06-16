<?php

namespace Tests\Lib;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use SeoApi\Client\HttpClientFactory;
use SplQueue;

trait RequestTesterTrait
{
    /** @var HttpClientFactory */
    protected $httpClientFactory;
    /** @var SplQueue */
    protected $requestsExpected;
    /** @var RequestAssertionsHandler */
    protected $requestQueue;

    public function setupRequestTester(): void
    {
        $this->requestQueue = new RequestAssertionsHandler;
        $this->httpClientFactory = new HttpClientFactory(
            HandlerStack::create($this->requestQueue)
        );
        $this->requestsExpected = new SplQueue();
    }

    protected static function jsonOkResponse(array $jsonData): Response
    {
        return new Response(200, [], json_encode($jsonData));
    }

    protected static function expectRequest(): RequestExpectation
    {
        return new RequestExpectation();
    }

    protected function expectResponse(RequestExpectation $request, Response $response): void
    {
        $this->requestQueue->enqueue($request, $response);
    }
}