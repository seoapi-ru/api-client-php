<?php

namespace Tests\Lib;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SplQueue;
use function GuzzleHttp\Promise\promise_for;

final class RequestAssertionsHandler
{
    /** @var SplQueue */
    private $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function enqueue(RequestExpectation $requestExpectation, ResponseInterface $response)
    {
        $this->queue->push([$requestExpectation, $response]);
    }

    public function __invoke(RequestInterface $request, array $options)
    {
        [$requestExpectation, $response] = $this->queue->dequeue();
        Assert::assertThat($request, $requestExpectation);

        return promise_for($response);
    }
}