<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Disconnect to ensure clean state for next test
        // This prevents "There is already an active transaction" errors with MySQL + RefreshDatabase
        DB::disconnect();
        parent::tearDown();
    }
}
