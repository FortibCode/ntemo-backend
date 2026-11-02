<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected User $adminOrgA;
    protected User $adminOrgB;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer Organisation A et B
        $this->orgA = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Brazzaville',
            'slug' => 'ntemo-brazza',
            'is_active' => true,
        ]);

        $this->orgB = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Pointe-Noire',
            'slug' => 'ntemo-pnr',
            'is_active' => true,
        ]);

        // Permissions
        $pView = Permission::create(['id' => (string) Str::uuid(), 'name' => 'Voir', 'slug' => 'employees.view_all', 'module' => 'employees']);
        $pCreate = Permission::create(['id' => (string) Str::uuid(), 'name' => 'Créer', 'slug' => 'employees.create_edit', 'module' => 'employees']);
        $pDelete = Permission::create(['id' => (string) Str::uuid(), 'name' => 'Supprimer', 'slug' => 'employees.delete', 'module' => 'employees']);

        // Rôles Org A
        $roleA = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'name' => 'HR Org A',
            'slug' => 'hr_a',
        ]);
        $roleA->permissions()->attach([$pView->id, $pCreate->id, $pDelete->id]);

        // Rôles Org B
        $roleB = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'name' => 'HR Org B',
            'slug' => 'hr_b',
        ]);
        $roleB->permissions()->attach([$pView->id, $pCreate->id, $pDelete->id]);

        // Utilisateurs
        $this->adminOrgA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'name' => 'Admin A',
            'email' => 'admin_a@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminOrgA->roles()->attach($roleA->id);

        $this->adminOrgB = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'name' => 'Admin B',
            'email' => 'admin_b@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminOrgB->roles()->attach($roleB->id);
    }

    public function test_can_create_employee_with_auto_generated_matricule(): void
    {
        $payload = [
            'first_name' => 'Fortune',
            'last_name' => 'Okombi',
            'email' => 'fortune@ntemo.local',
            'hire_date' => '2026-01-15',
            'gender' => 'M',
        ];

        $response = $this->actingAs($this->adminOrgA, 'sanctum')
            ->postJson('/api/v1/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Fortune')
            ->assertJsonPath('data.last_name', 'Okombi')
            ->assertJsonPath('data.matricule', 'NTM-2026-0001');

        $this->assertDatabaseHas('employees', [
            'organization_id' => $this->orgA->id,
            'email' => 'fortune@ntemo.local',
            'matricule' => 'NTM-2026-0001',
        ]);

        $this->assertDatabaseHas('employee_assignments', [
            'organization_id' => $this->orgA->id,
            'reason' => 'EMBAUCHE',
        ]);
    }

    public function test_isolation_between_organizations_cannot_view_other_org_employee(): void
    {
        // Créer un employé dans Org A
        $employeeA = Employee::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'matricule' => 'NTM-2026-0001',
            'first_name' => 'Secret',
            'last_name' => 'Agent A',
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        // Tentative d'accès par l'admin de l'Org B
        $response = $this->actingAs($this->adminOrgB, 'sanctum')
            ->getJson("/api/v1/employees/{$employeeA->id}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/employees');
        $response->assertStatus(401);
    }
}

