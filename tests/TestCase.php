<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Force tests onto the dedicated test database, even if a stale
     * bootstrap/cache/config.php points elsewhere. This must run before
     * setUpTraits() triggers RefreshDatabase's migrate:fresh — otherwise
     * that migration could wipe the dev database.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        config(['database.connections.mysql.database' => 'online_application_test']);

        // Drop any connection resolved during boot so the next query
        // resolves with the test database name.
        DB::purge('mysql');
    }
}
