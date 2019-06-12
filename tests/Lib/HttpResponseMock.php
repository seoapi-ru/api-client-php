<?php

namespace Tests\Lib;

use Symfony\Contracts\HttpClient\ResponseInterface;
use function json_encode;

final class HttpResponseMock implements ResponseInterface
{
    /** @var int */
    private $code;
    /** @var array */
    private $data;

    public function __construct(int $code, array $data)
    {
        $this->code = $code;
        $this->data = $data;
    }

    public function getStatusCode(): int
    {
        return $this->code;
    }

    public function getHeaders(bool $throw = true): array
    {
        throw new \BadMethodCallException('GetHeaders are not supported');
    }

    public function getContent(bool $throw = true): string
    {
        return json_encode($this->data);
    }

    public function toArray(bool $throw = true): array
    {
        return $this->data;
    }

    public function cancel(): void
    {
        throw new \BadMethodCallException('Cancel is not supported');
    }

    public function getInfo(string $type = null)
    {
        throw new \BadMethodCallException('GetInfo is not supported');
    }
}