<?php

namespace Tests\Feature;

use App\Mail\ApplicationSubmittedMail;
use App\Models\Admin\Program;
use App\Models\Admin\Role;
use App\Models\Admin\SchoolYear;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use App\Support\NotificationSettings;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingsPageFixesTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $schoolYear;

    private Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);

        $this->schoolYear = SchoolYear::create([
            'start' => '2026',
            'end' => '2027',
            'name' => 'SY 2026 - 2027',
            'is_active' => true,
            'application_start' => today()->subDays(10)->toDateString(),
            'application_deadline' => today()->addDays(10)->toDateString(),
        ]);

        $this->applicant = Applicant::create([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'application_status' => 'pending',
        ]);
    }

    private function admin(): User
    {
        $role = Role::where('name', 'System Administrator')->firstOrFail();
        $admin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    /**
     * Mutate the real landing_settings.json (snapshot restored by the base
     * TestCase); successive calls accumulate on top of the current file.
     */
    private function writeSettings(callable $mutate): void
    {
        $path = storage_path('app/landing_settings.json');

        if (! $this->touchedLandingSettings) {
            $this->touchedLandingSettings = true;
            $this->originalLandingSettings = file_exists($path) ? file_get_contents($path) : null;
        }

        $current = file_exists($path) ? file_get_contents($path) : null;
        $settings = $current !== null ? (json_decode($current, true) ?? []) : [];

        $mutate($settings);

        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function setAppSettings(array $overrides): void
    {
        $this->writeSettings(function (array &$settings) use ($overrides) {
            $settings['app_settings'] = array_merge($settings['app_settings'] ?? [], $overrides);
        });
    }

    private function setNotificationFlag(int $eventId, bool $email): void
    {
        $this->writeSettings(function (array &$settings) use ($eventId, $email) {
            $notifications = $settings['notification_settings'] ?? [];
            $found = false;

            foreach ($notifications as &$notification) {
                if (($notification['id'] ?? null) === $eventId) {
                    $notification['email'] = $email;
                    $found = true;
                }
            }
            unset($notification);

            if (! $found) {
                $notifications[] = [
                    'id' => $eventId,
                    'event' => 'Test Event',
                    'email' => $email,
                    'description' => '',
                ];
            }

            $settings['notification_settings'] = $notifications;
        });
    }

    // ── allow_google_login ────────────────────────────────────────────────────

    public function test_disabled_google_login_redirects_applicants_away_from_google(): void
    {
        $this->setAppSettings(['allow_google_login' => false]);

        $this->get(route('applicant.google.redirect'))
            ->assertRedirect(route('applicant.login'))
            ->assertSessionHas('error');

        $this->get('/applicant/auth/google/callback')
            ->assertRedirect(route('applicant.login'))
            ->assertSessionHas('error');
    }

    public function test_enabled_google_login_still_redirects_to_google(): void
    {
        $response = $this->get(route('applicant.google.redirect'));

        $response->assertStatus(302);
        $this->assertSame(
            'accounts.google.com',
            parse_url($response->headers->get('Location'), PHP_URL_HOST)
        );
    }

    // ── auto_send_email + notification event flags ────────────────────────────

    public function test_notification_gates_respect_auto_send_switch_and_event_flags(): void
    {
        // No stored switches at all → everything defaults to enabled.
        $this->writeSettings(function (array &$settings) {
            unset($settings['app_settings']['auto_send_email']);
            $settings['notification_settings'] = [];
        });
        $this->assertTrue(NotificationSettings::shouldSend(NotificationSettings::EVENT_APPROVED));
        $this->assertTrue(NotificationSettings::shouldSend(NotificationSettings::EVENT_SUBMITTED));

        // The global switch off silences every event.
        $this->setAppSettings(['auto_send_email' => false]);
        $this->assertFalse(NotificationSettings::shouldSend(NotificationSettings::EVENT_APPROVED));
        $this->assertFalse(NotificationSettings::shouldSend(NotificationSettings::EVENT_SUBMITTED));

        // Global switch back on, one event off → only that event is silenced.
        $this->setAppSettings(['auto_send_email' => true]);
        $this->setNotificationFlag(NotificationSettings::EVENT_APPROVED, false);
        $this->assertFalse(NotificationSettings::shouldSend(NotificationSettings::EVENT_APPROVED));
        $this->assertTrue(NotificationSettings::shouldSend(NotificationSettings::EVENT_REJECTED));
    }

    public function test_submission_email_template_renders(): void
    {
        $program = Program::where('initials', 'SPICT')->firstOrFail();

        $html = (new ApplicationSubmittedMail($this->applicant, $program))->render();

        $this->assertStringContainsString('Application Received', $html);
        $this->assertStringContainsString('Juan', $html);
    }

    // ── Application Submitted email behaviour ─────────────────────────────────

    public function test_submission_email_is_sent_when_enabled(): void
    {
        Mail::fake();
        $this->setAppSettings(['auto_send_email' => true]);
        $this->setNotificationFlag(NotificationSettings::EVENT_SUBMITTED, true);

        $this->submitApplication();

        Mail::assertSent(
            ApplicationSubmittedMail::class,
            fn (ApplicationSubmittedMail $mail) => $mail->hasTo($this->applicant->email)
        );
    }

    public function test_submission_email_is_skipped_when_auto_send_is_off(): void
    {
        Mail::fake();
        $this->setAppSettings(['auto_send_email' => false]);

        $this->submitApplication();

        Mail::assertNotSent(ApplicationSubmittedMail::class);
    }

    public function test_submission_email_is_skipped_when_event_flag_is_off(): void
    {
        Mail::fake();
        $this->setNotificationFlag(NotificationSettings::EVENT_SUBMITTED, false);

        $this->submitApplication();

        Mail::assertNotSent(ApplicationSubmittedMail::class);
    }

    private function submitApplication(): void
    {
        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application/select-program', ['program' => 'SPICT'])
            ->assertStatus(200);

        $this->actingAs($this->applicant, 'applicant')
            ->postJson('/api/applicant/application', [
                'lastname' => 'Dela Cruz',
                'firstname' => 'Juan',
                'date_of_birth' => '2015-06-15',
                'place_of_birth' => 'Carranglan',
                'citizenship' => 'Filipino',
                'sex' => 'Male',
                'complete_address' => 'Barangay Poblacion, Carranglan, Nueva Ecija',
                'parent_guardian_name' => 'Maria Dela Cruz',
                'contact_number' => '09171234567',
                'elementary_school' => 'Carranglan National High School',
                'elementary_school_address' => 'Carranglan, Nueva Ecija',
            ])
            ->assertStatus(200);
    }

    // ── Requirements trash + restore ──────────────────────────────────────────

    public function test_requirements_can_be_restored_from_the_trash(): void
    {
        $admin = $this->admin();
        $name = 'Restoreable Requirement '.Str::uuid();

        $this->actingAs($admin)->postJson('/api/requirements', [
            'name' => $name,
            'is_required' => 1,
            'is_active' => 1,
        ])->assertStatus(200);

        $created = collect($this->actingAs($admin)->getJson('/api/requirements')->json('data'))
            ->firstWhere('name', $name);
        $this->assertNotNull($created);
        $id = $created['id'];

        $this->actingAs($admin)->deleteJson("/api/requirements/{$id}")->assertStatus(200);

        $activeNames = collect($this->actingAs($admin)->getJson('/api/requirements')->json('data'))
            ->pluck('name')->all();
        $this->assertNotContains($name, $activeNames);

        $deletedNames = collect($this->actingAs($admin)->getJson('/api/requirements?deleted=1')->json('data'))
            ->pluck('name')->all();
        $this->assertContains($name, $deletedNames);

        $this->actingAs($admin)->postJson("/api/requirements/{$id}/restore")->assertStatus(200);

        $activeNames = collect($this->actingAs($admin)->getJson('/api/requirements')->json('data'))
            ->pluck('name')->all();
        $this->assertContains($name, $activeNames);
    }

    // ── School year period validation ─────────────────────────────────────────

    public function test_school_year_deadline_must_not_precede_the_start(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/school-years', [
            'school_year_start' => '2030',
            'school_year_end' => '2031',
            'application_start' => '2030-06-01',
            'application_deadline' => '2030-05-01',
        ])->assertStatus(422);

        // A deadline without a start date stays valid (open-ended period).
        $this->actingAs($admin)->postJson('/api/school-years', [
            'school_year_start' => '2031',
            'school_year_end' => '2032',
            'application_deadline' => '2031-07-15',
        ])->assertStatus(200);
    }

    // ── Audit trail for settings writes ───────────────────────────────────────

    public function test_settings_page_writes_write_audit_logs(): void
    {
        $admin = $this->admin();

        // Snapshot the JSON file first so a controller write stays reversible.
        $this->writeSettings(function (array &$settings) {
            // Intentional no-op — only triggers the snapshot/restore handling.
        });

        $this->actingAs($admin)->postJson('/api/landing-settings', [
            'school_name' => 'CNHS Online Application',
            'title' => 'Welcome to CNHS Online Application System',
            'description' => 'Apply for admission easily and conveniently from anywhere, anytime.',
            'announcement' => '',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'Settings',
            'description' => 'Updated landing page settings',
        ]);

        $this->actingAs($admin)->postJson('/api/landing-settings/app-settings', [
            'maintenance_mode' => 0,
            'registration_open' => 1,
            'allow_google_login' => 1,
            'auto_send_email' => 1,
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'Settings',
            'description' => 'Updated application settings',
        ]);

        $this->actingAs($admin)->postJson('/api/landing-settings/notifications', [
            'notifications' => [
                ['id' => 1, 'email' => true],
                ['id' => 2, 'email' => true],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'Settings',
            'description' => 'Updated notification settings',
        ]);

        $this->actingAs($admin)->postJson('/api/session-timeout-settings', [
            'timeout_minutes' => 30,
            'warning_minutes' => 3,
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'Settings',
            'description' => 'Updated session timeout settings',
        ]);
    }
}
