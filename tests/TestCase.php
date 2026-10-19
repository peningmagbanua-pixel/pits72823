<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected bool $touchedLandingSettings = false;

    protected ?string $originalLandingSettings = null;

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

    /**
     * The controllers read registration_open from the real settings file,
     * so tests that exercise registration must set it explicitly instead
     * of depending on the machine's saved admin setting. The original
     * file content is restored in tearDown().
     */
    protected function setRegistrationOpen(bool $open): void
    {
        $path = storage_path('app/landing_settings.json');

        if (!$this->touchedLandingSettings) {
            $this->touchedLandingSettings = true;
            $this->originalLandingSettings = file_exists($path) ? file_get_contents($path) : null;
        }

        $settings = $this->originalLandingSettings !== null
            ? (json_decode($this->originalLandingSettings, true) ?? [])
            : [];
        $settings['app_settings']['registration_open'] = $open;

        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        if ($this->touchedLandingSettings) {
            $path = storage_path('app/landing_settings.json');

            if ($this->originalLandingSettings === null) {
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {
                file_put_contents($path, $this->originalLandingSettings);
            }
        }

        parent::tearDown();
    }
}
