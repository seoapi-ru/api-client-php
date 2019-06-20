<?php

namespace Tests\Lib;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsAnything;
use function GuzzleHttp\Psr7\parse_query;

final class RequestExpectation extends Constraint
{
    /** @var Constraint */
    private $method;
    /** @var Constraint */
    private $query;
    /** @var Constraint */
    private $payload;
    /** @var Constraint */
    private $headers;
    /** @var Constraint */
    private $path;

    public function __construct()
    {
        parent::__construct();
        $this->method = new IsAnything();
        $this->headers = new IsAnything();
        $this->query = new IsAnything();
        $this->payload = new IsAnything();
        $this->path = new IsAnything();
    }

    public function withMethod(Constraint $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function withQuery(Constraint $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function withPayload(Constraint $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function withHeaders(Constraint $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function withPath(Constraint $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function toString(): string
    {
        return 'is equal to HTTP request';
    }

    public function evaluate($other, $description = '', $returnResult = false)
    {
        $request = $other;
        if (!$request instanceof Request) {
            if ($returnResult) {
                return false;
            }
            $this->fail($other, sprintf("Request is not a %s instance", Request::class));
        }
        $query = parse_query($request->getUri()->getQuery());
        $method = $request->getMethod();
        $headers = $request->getHeaders();
        $payload = $request->getBody()->getContents();
        $path = $request->getUri()->getPath();

        if ($returnResult) {
            return $this->method->evaluate($method, '', true)
                && $this->headers->evaluate($headers, '', true)
                && $this->payload->evaluate($payload, '', true)
                && $this->query->evaluate($query, '', true)
                && $this->path->evaluate($path, '', true);
        } else {
            Assert::assertThat($method, $this->method);
            Assert::assertThat($headers, $this->headers);
            Assert::assertThat($payload, $this->payload);
            Assert::assertThat($query, $this->query);
            Assert::assertThat($path, $this->path);
        }

        return true;
    }
}