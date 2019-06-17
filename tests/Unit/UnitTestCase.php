<?php

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\TestCase;

class UnitTestCase extends TestCase
{
    /** @var \Faker\Generator */
    protected $faker;

    protected function setUp()
    {
        parent::setUp();
        $this->faker = Factory::create('ru');
    }
}