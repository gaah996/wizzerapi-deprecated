<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $senderHash = 'a49435250024fbe8285e5de571a1b32adb6d6eeff291311a09cfd0d159b00eec';
    protected $creditCardToken = 'ead9f1aa7ecb48f884de6e1571152bae';
}
