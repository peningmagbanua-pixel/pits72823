<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DynamicLogoTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_LOGO = 'landing/temp-logo-check.png';

    private function setLogoPath(): void
    {
        $path = storage_path('app/landing_settings.json');

        if (!$this->touchedLandingSettings) {
            $this->touchedLandingSettings = true;
            $this->originalLandingSettings = file_exists($path) ? file_get_contents($path) : null;
        }

        $settings = $this->originalLandingSettings !== null
            ? (json_decode($this->originalLandingSettings, true) ?? [])
            : [];
        $settings['logo_path'] = self::TEST_LOGO;

        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function test_public_pages_render_updated_logo(): void
    {
        $this->setLogoPath();

        foreach (['/', '/admin/login', '/applicant/login', '/applicant/register'] as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200);
            $this->assertStringContainsString(
                self::TEST_LOGO,
                $response->getContent(),
                "Page {$uri} does not render the uploaded logo."
            );
        }
    }

    public function test_dashboard_blades_expose_logo_to_spa(): void
    {
        $this->setLogoPath();

        foreach (['admin.dashboard', 'applicant.dashboard'] as $view) {
            $html = view($view)->render();

            $this->assertStringContainsString('window.__LANDING_SETTINGS__', $html, $view);
            // @json escapes slashes — normalise before asserting the URL.
            $this->assertStringContainsString(
                'temp-logo-check.png',
                str_replace('\\', '', $html),
                "{$view} does not expose the uploaded logo URL."
            );
        }
    }

    public function test_fallback_logo_is_used_when_none_is_uploaded(): void
    {
        // No custom logo uploaded on this machine state — pages still render.
        $path = storage_path('app/landing_settings.json');
        if (!$this->touchedLandingSettings) {
            $this->touchedLandingSettings = true;
            $this->originalLandingSettings = file_exists($path) ? file_get_contents($path) : null;
        }
        $settings = $this->originalLandingSettings !== null
            ? (json_decode($this->originalLandingSettings, true) ?? [])
            : [];
        unset($settings['logo_path']);
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        foreach (['/admin/login', '/applicant/login', '/applicant/register'] as $uri) {
            $this->get($uri)
                ->assertStatus(200)
                ->assertSee('images/cnhs_logo.png', false);
        }

        $html = view('admin.dashboard')->render();
        $this->assertStringContainsString('images/cnhs_logo.png', str_replace('\\', '', $html));
    }
}
