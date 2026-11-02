<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\BadgeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BadgeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $hrUser;
    protected User $employeeUser;
    protected Employee $employee;
    protected BadgeService $badgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->badgeService = app(BadgeService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Badge Org',
            'slug' => 'ntemo-badge',
            'is_active' => true,
        ]);

        $permBadge = Permission::create([
            'id' => (string) Str::uuid(),
            'name' => 'Badges Manage',
            'slug' => 'badges.generate_revoke',
            'module' => 'badges',
        ]);

        $roleHr = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'HR Role',
            'slug' => 'hr_role',
        ]);
        $roleHr->permissions()->attach($permBadge->id);

        $this->hrUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'RH Admin',
            'email' => 'rh@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->hrUser->roles()->attach($roleHr->id);

        $this->employeeUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Alice Martin',
            'email' => 'alice@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'user_id' => $this->employeeUser->id,
            'matricule' => 'NTM-2026-0088',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_hr_can_issue_badge_and_employee_can_view_virtual_badge(): void
    {
        // 1. Émission du badge par les RH
        $response = $this->actingAs($this->hrUser, 'sanctum')
            ->postJson('/api/v1/badges/issue', [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('badges', [
            'employee_id' => $this->employee->id,
            'status' => 'ACTIVE',
        ]);

        // 2. Consultation par le collaborateur de son badge virtuel
        $myBadgeResponse = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/badges/my-badge');

        $myBadgeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.employee.matricule', 'NTM-2026-0088');
    }

    public function test_reissuing_badge_automatically_revokes_old_badge(): void
    {
        $badge1 = $this->badgeService->issueBadge($this->employee, null, $this->hrUser);
        $this->assertEquals('ACTIVE', $badge1->status);

        // Réémission d'un second badge
        $badge2 = $this->badgeService->issueBadge($this->employee, null, $this->hrUser);

        $badge1->refresh();
        $this->assertEquals('REPLACED', $badge1->status);
        $this->assertNotNull($badge1->revoked_at);

        $this->assertEquals('ACTIVE', $badge2->status);
    }

    public function test_revoked_badge_fails_security_verification(): void
    {
        $badge = $this->badgeService->issueBadge($this->employee, null, $this->hrUser);

        // Révocation manuelle
        $this->actingAs($this->hrUser, 'sanctum')
            ->postJson("/api/v1/badges/{$badge->id}/revoke", [
                'reason' => 'Perte du badge physique déclarée.',
            ])->assertStatus(200);

        // Tentative de vérification du jeton au scan de contrôle
        $verifyResponse = $this->actingAs($this->hrUser, 'sanctum')
            ->postJson('/api/v1/badges/verify', [
                'security_token' => $badge->security_token,
            ]);

        $verifyResponse->assertStatus(422)
            ->assertJsonPath('is_valid', false);
    }
}

