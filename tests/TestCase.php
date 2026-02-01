<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Avoid "There is already an active transaction" with MySQL + RefreshDatabase:
        // disconnect so the next test gets a fresh connection with no leftover transaction
        DB::disconnect();
        parent::tearDown();
    }
}
