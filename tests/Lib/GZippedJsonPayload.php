<?php

namespace Tests\Lib;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use UnexpectedValueException;
use function gzdecode;
use function is_string;
use function json_decode;

/**
 * @deprecated Unless will be needed later
 */
class GZippedJsonPayload extends Constraint
{
    /** @var array */
    private $payload;

    public function __construct(array $payload)
    {
        parent::__construct();
        $this->payload = $payload;
    }

    public function evaluate($other, $description = '', $returnResult = false)
    {
        if (!is_string($other)) {
            throw new UnexpectedValueException("GZIPped data should be a string");
        }
        $gzippedActual = json_decode(gzdecode($other), true);

        return Assert::equalTo($this->payload)->evaluate($gzippedActual, '', $returnResult);
    }

    public function toString(): string
    {
        return ' value is gzipped';
    }
}