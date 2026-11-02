<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $managerUser;
    protected User $hrUser;
    protected User $employeeUser;
    protected Employee $employee;
    protected LeaveType $paidLeaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Leave Org',
            'slug' => 'ntemo-leave',
            'is_active' => true,
        ]);

        $permN1 = Permission::create(['id' => (string) Str::uuid(), 'name' => 'Approve N1', 'slug' => 'leaves.approve_n1', 'module' => 'leaves']);
        $permHr = Permission::create(['id' => (string) Str::uuid(), 'name' => 'Approve HR', 'slug' => 'leaves.approve_final_rh', 'module' => 'leaves']);

        $roleManager = Role::create(['id' => (string) Str::uuid(), 'organization_id' => $this->org->id, 'name' => 'Manager', 'slug' => 'manager']);
        $roleManager->permissions()->attach($permN1->id);

        $roleHr = Role::create(['id' => (string) Str::uuid(), 'organization_id' => $this->org->id, 'name' => 'HR', 'slug' => 'hr']);
        $roleHr->permissions()->attach($permHr->id);

        $this->managerUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Manager Marc',
            'email' => 'marc@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->managerUser->roles()->attach($roleManager->id);

        $this->hrUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Directeur RH',
            'email' => 'drh@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->hrUser->roles()->attach($roleHr->id);

        $this->employeeUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Paul Employé',
            'email' => 'paul@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'user_id' => $this->employeeUser->id,
            'matricule' => 'NTM-2026-0099',
            'first_name' => 'Paul',
            'last_name' => 'Employé',
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->paidLeaveType = LeaveType::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Congé Payé Annuel',
            'code' => 'CP',
            'default_days_per_year' => 26.0,
            'is_paid' => true,
            'requires_manager_approval' => true,
            'requires_hr_approval' => true,
            'is_active' => true,
        ]);

        // Solde initial : 26 jours alloués
        LeaveBalance::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->paidLeaveType->id,
            'year' => 2026,
            'allocated_days' => 26.0,
            'used_days' => 0.0,
            'pending_days' => 0.0,
            'remaining_days' => 26.0,
        ]);
    }

    public function test_complete_two_tier_leave_approval_workflow(): void
    {
        // 1. Dépôt de la demande par l'employé : du Lundi 2026-11-09 au Vendredi 2026-11-13 (5 jours ouvrés)
        $submitResponse = $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/leaves/requests', [
                'leave_type_id' => $this->paidLeaveType->id,
                'start_date' => '2026-11-09',
                'end_date' => '2026-11-13',
                'reason' => 'Vacances annuelles en famille.',
            ]);

        $submitResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PENDING_N1')
            ->assertJsonPath('data.total_days', '5.00');

        $requestId = $submitResponse->json('data.id');

        // Vérification de la réservation du solde
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'pending_days' => 5.0,
            'remaining_days' => 21.0,
        ]);

        // 2. Approbation par le Manager N+1
        $n1Response = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson("/api/v1/leaves/requests/{$requestId}/review-n1", [
                'approved' => true,
                'comments' => 'Avis favorable, bonne continuité de service.',
            ]);

        $n1Response->assertStatus(200)
            ->assertJsonPath('data.status', 'PENDING_HR');

        // 3. Approbation finale par le Directeur RH
        $hrResponse = $this->actingAs($this->hrUser, 'sanctum')
            ->postJson("/api/v1/leaves/requests/{$requestId}/review-hr", [
                'approved' => true,
                'comments' => 'Validation RH définitive.',
            ]);

        $hrResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'APPROVED');

        // Vérification de la déduction finale du solde (5j consommés, 21j restants)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'pending_days' => 0.0,
            'used_days' => 5.0,
            'remaining_days' => 21.0,
        ]);
    }

    public function test_manager_rejection_restores_pending_balance(): void
    {
        // Demande de 5 jours
        $submit = $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/leaves/requests', [
                'leave_type_id' => $this->paidLeaveType->id,
                'start_date' => '2026-11-09',
                'end_date' => '2026-11-13',
                'reason' => 'Congé.',
            ]);

        $requestId = $submit->json('data.id');

        // Rejet N+1
        $reject = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson("/api/v1/leaves/requests/{$requestId}/review-n1", [
                'approved' => false,
                'comments' => 'Période de clôture projet, report demandé.',
            ]);

        $reject->assertStatus(200)
            ->assertJsonPath('data.status', 'REJECTED');

        // Le solde doit être intégralement restauré (0 en attente, 26 restants)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'pending_days' => 0.0,
            'remaining_days' => 26.0,
        ]);
    }
}
