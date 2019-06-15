<?php

namespace SeoApi\Client;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

final class HttpClientFactory
{
    /** @var HandlerStack */
    private $handlerStack;

    public function __construct(HandlerStack $handlerStack = null)
    {
        $this->handlerStack = $handlerStack ?? HandlerStack::create();
    }

    public function create(string $baseUri): Client
    {
        $client = new Client(['base_uri' => $baseUri, 'handler' => $this->handlerStack]);

        return $client;
    }
}