<?php

namespace Tests\Unit\Lib;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\Lib\FormPayload;

class ConstraintsTest extends TestCase
{
    public function provideValidFormPayloads()
    {
        return [
            ['foo=1&bar=2', ['bar' => '2', 'foo' => '1']],
        ];
    }

    /**
     * @test
     * @dataProvider provideValidFormPayloads
     *
     * @param string $valid
     * @param array $actual
     */
    public function formPayloadValidAssertions(string $valid, array $actual)
    {
        $constraint = new FormPayload($actual);
        self::assertThat($valid, $constraint);
    }

    public function provideInvalidFormPayloads()
    {
        return [
            ['foo=1&bar=2', ['foo' => '1']],
        ];
    }

    /**
     * @test
     * @dataProvider provideInvalidFormPayloads
     *
     * @param string $invalid
     * @param array $actual
     */
    public function formPayloadInvalidAssertions(string $invalid, array $actual)
    {
        $constraint = new FormPayload($actual);
        $this->expectException(AssertionFailedError::class);

        self::assertThat($invalid, $constraint);
    }
}