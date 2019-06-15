<?php

namespace Tests\Lib;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use function http_build_query;
use function ksort;
use function League\Uri\parse_query;

final class FormPayload extends Constraint
{
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        parent::__construct();
        ksort($data);
        $this->data = $data;
    }

    public function contentEquals($content): bool
    {
        return http_build_query($this->data) === $content;
    }

    protected function matches($other): bool
    {
        Assert::assertIsString($other);

        $dataParsed = parse_query($other);
        if ($this->data === [] && empty($dataParsed)) {
            return true;
        }
        ksort($dataParsed);

        Assert::assertSame($this->data, $dataParsed);

        return true;
    }

    public function toString(): string
    {
        return 'is an exact HTTP form payload';
    }
}