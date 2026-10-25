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
     * Run a test-only users table migration followed by the package's real
     * migrations, so the suites exercise exactly what Keystone ships.
     *
     * @return array
     */
    protected function migrateFreshUsing()
    {
        return array_merge($this->baseMigrateFreshUsing(), [
            '--path' => [
                realpath(__DIR__.'/database/migrations'),
                realpath(__DIR__.'/../database/migrations'),
            ],
            '--realpath' => true,
        ]);
    }
}
