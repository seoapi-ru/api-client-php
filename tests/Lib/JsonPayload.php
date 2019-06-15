<?php

namespace Tests\Lib;

use PHPUnit\Framework\Constraint\JsonMatches;
use function json_encode;

final class JsonPayload extends JsonMatches
{
    public function __construct(array $data)
    {
        parent::__construct(json_encode($data));
    }
}