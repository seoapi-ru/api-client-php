<?php

namespace Tests\Lib;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use function explode;
use function sort;
use function var_export;

class HeadersSet extends Constraint
{
    /** @var array */
    private $expectHeaders;

    public function __construct(array $expectHeaders)
    {
        parent::__construct();
        $this->expectHeaders = $expectHeaders;
    }

    public function evaluate($other, $description = '', $returnResult = false)
    {
        if (empty($this->expectHeaders)) {
            $result = Assert::isEmpty()
                            ->evaluate(
                                $other,
                                'Headers must be empty list, headers given: '.var_export($other, true),
                                $returnResult
                            )
            ;

            return $result;
        }

        foreach ($this->expectHeaders as $name => $expectHeaderValues) {
            $result = Assert::arrayHasKey($name)
                            ->evaluate($other, "Header '{$name}' must present", $returnResult)
            ;
            if (!$result && $returnResult) {
                return $result;
            }
            $expectHeaderValues = $this->normalizeHeaderValues($expectHeaderValues);

            $result = Assert::equalTo($expectHeaderValues)
                            ->evaluate($this->normalizeHeaderValues($other[$name]), '', $returnResult)
            ;
            if (!$result && $returnResult) {
                return $result;
            }
        }

        return true;
    }

    public function toString(): string
    {
        return ' headers are contained in Request';
    }

    /**
     * @param string|array $headerValues
     * @return array
     */
    private function normalizeHeaderValues($headerValues): array
    {
        if (\is_string($headerValues)) {
            $headerValues = \array_map('trim', explode(',', $headerValues));
        }
        $headerValues = (array)$headerValues;
        sort($headerValues);

        return $headerValues;
    }
}