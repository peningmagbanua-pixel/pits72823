<?php

namespace Tests\Feature;

use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\Role;
use App\Models\Admin\User;
use App\Models\Applicant\Applicant;
use Database\Seeders\ProgramsSeeder;
use Database\Seeders\RequirementsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequirementsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesTableSeeder::class);
        $this->seed(ProgramsSeeder::class);
        $this->seed(RequirementsTableSeeder::class);

        $this->sysadmin = User::create([
            'firstname' => 'Test',
            'lastname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
        $this->sysadmin->roles()->attach(
            Role::where('name', 'System Administrator')->firstOrFail()->id
        );
    }

    private function makeApplicant(): Applicant
    {
        return Applicant::create([
            'firstname' => 'Test',
            'lastname' => 'Applicant',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
        ]);
    }

    public function test_admin_can_list_requirements(): void
    {
        $response = $this->actingAs($this->sysadmin)->getJson('/api/requirements');

        $response->assertStatus(200);
        $this->assertCount(6, $response->json('data'));
    }

    public function test_admin_can_create_update_and_delete_requirement(): void
    {
        $ste = Program::where('initials', 'STE')->firstOrFail();

        $this->actingAs($this->sysadmin)
            ->postJson('/api/requirements', [
                'name' => 'Birth Certificate',
                'program_ids' => [$ste->id],
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Requirement created successfully!']);

        $requirement = Requirement::where('name', 'Birth Certificate')->firstOrFail();
        $this->assertSame(['STE'], $requirement->programs()->pluck('initials')->all());

        // Deactivate — same call the Settings toggle makes: program_ids is
        // deliberately absent, so the assignment must survive.
        $this->actingAs($this->sysadmin)
            ->putJson('/api/requirements/'.$requirement->id, [
                'name' => 'Birth Certificate',
                'is_required' => 1,
                'is_active' => 0,
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Requirement updated successfully!']);

        $this->assertFalse($requirement->fresh()->is_active);
        $this->assertSame(
            ['STE'],
            $requirement->fresh()->programs()->pluck('initials')->all(),
            'Toggling a requirement must not clear its programs.'
        );

        $this->actingAs($this->sysadmin)
            ->deleteJson('/api/requirements/'.$requirement->id)
            ->assertStatus(200)
            ->assertJson(['message' => 'Requirement deleted successfully!']);

        $this->assertSoftDeleted('requirements', ['id' => $requirement->id]);
    }

    public function test_programs_are_reported_and_only_real_programs_are_accepted(): void
    {
        $data = collect($this->actingAs($this->sysadmin)->getJson('/api/requirements')->assertOk()->json('data'));

        $health = $data->firstWhere('name', 'Health Declaration Form');
        $this->assertSame('STE', $health['programs'][0]['initials']);
        $this->assertArrayHasKey('id', $health['programs'][0]);

        $sf9 = $data->firstWhere('name', 'SF9 / Report Card');
        $this->assertCount(0, $sf9['programs'], 'No programs = required by every program.');

        // A program id that does not exist (or a hardcoded string) is rejected.
        $this->actingAs($this->sysadmin)
            ->postJson('/api/requirements', [
                'name' => 'Bogus Program Requirement',
                'program_ids' => [Str::uuid()],
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertStatus(422);

        $this->actingAs($this->sysadmin)
            ->postJson('/api/requirements', [
                'name' => 'Hardcoded Program Requirement',
                'program_ids' => ['STE'],
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_duplicate_requirement_name_is_rejected(): void
    {
        $this->actingAs($this->sysadmin)
            ->postJson('/api/requirements', [
                'name' => 'SF9 / Report Card',
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_applicant_requirements_endpoint_returns_only_active_requirements(): void
    {
        $response = $this->actingAs($this->makeApplicant(), 'applicant')
            ->getJson('/api/applicant/requirements');

        $response->assertStatus(200);

        $names = collect($response->json())->pluck('name');

        $this->assertTrue($names->contains('SF9 / Report Card'));
        $this->assertTrue($names->contains('Certificate of Good Moral Character'));
        $this->assertFalse($names->contains('PSA Birth Certificate'));
        $this->assertFalse($names->contains('2x2 ID Photo'));

        // The form filters on these ids: empty = every program.
        $byName = collect($response->json())->keyBy('name');
        $this->assertCount(0, $byName->get('SF9 / Report Card')['program_ids']);
        $this->assertSame(
            [Program::where('initials', 'STE')->firstOrFail()->id],
            $byName->get('Certificate of Good Moral Character')['program_ids']
        );
    }
}
