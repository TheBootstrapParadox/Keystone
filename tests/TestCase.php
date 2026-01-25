<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        migrateFreshUsing as baseMigrateFreshUsing;
    }

    /**
     * The parameters that should be used when running "migrate:fresh".
     *
     * Use only test-specific migrations which include both the base
     * users table and copies of the package migrations.
     *
     * @return array
     */
    protected function migrateFreshUsing()
    {
        return array_merge($this->baseMigrateFreshUsing(), [
            '--path' => realpath(__DIR__ . '/database/migrations'),
            '--realpath' => true,
        ]);
    }
}
