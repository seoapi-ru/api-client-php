<?php

namespace Tests\Unit\Lib;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\TestCase;
use Tests\Lib\FormPayload;
use Tests\Lib\HeadersSet;

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

    public function provideHeaderSets()
    {
        $sampleSet = $validSet = [
            'Authorization' => 'Foo bar',
            'Content-Type' => ['Type1', 'Type2'],
        ];
        $validGuzzleSet = [
            'Authorization' => ['Foo bar'],
            'Content-Type' => ['Type1', 'Type2'],
        ];
        $validPlainSet = [
            'Authorization' => 'Foo bar',
            'Content-Type' => 'Type1, Type2',
        ];
        $emptySet = [];
        $randomSet = ['Anything' => 'AnyValue'];

        return [
            [$sampleSet, $validSet, self::isTrue()],
            [$sampleSet, $validPlainSet, self::isTrue()],
            [$sampleSet, $validGuzzleSet, self::isTrue()],
            [$sampleSet, $randomSet, self::isFalse()],
            [$sampleSet, [], self::isFalse()],
            [$emptySet, [], self::isTrue()],
            [$emptySet, $randomSet, self::isFalse()],
        ];
    }

    /**
     * @test
     * @dataProvider provideHeaderSets
     *
     * @param array $expectHeaders
     * @param array $testHeaders
     * @param Constraint $assertion
     */
    public function headersAssertion(array $expectHeaders, array $testHeaders, Constraint $assertion)
    {
        $constraint = new HeadersSet($expectHeaders);

        self::assertThat($constraint->evaluate($testHeaders, '', true), $assertion);
    }
}