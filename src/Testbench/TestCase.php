<?php

declare(strict_types=1);

namespace Switch\Foundation\Testbench;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switch\Router\Router;

abstract class TestCase extends BaseTestCase
{
    use MakesHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultHeaders = [];
        $this->authenticatedUser = null;
    }
}
