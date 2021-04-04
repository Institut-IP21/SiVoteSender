<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Synced with .env.testing
    public $token = '666666';

    use CreatesApplication;
}
