<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectTimesheetTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $managerUser;
    protected User $employeeUser;
    protected Employee $employee;
    protected Project $project;
    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Projects Org',
            'slug' => 'ntemo-projects',
            'is_active' => true,
        ]);

        $this->managerUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Chef de Projet',
            'email' => 'pm@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->employeeUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Ingénieur Dev',
            'email' => 'dev@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'user_id' => $this->employeeUser->id,
            'matricule' => 'NTM-2026-0055',
            'first_name' => 'Ingénieur',
            'last_name' => 'Dev',
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->project = Project::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Refonte SI NTEMO ONE',
            'code' => 'PRJ-N1-2026',
            'start_date' => '2026-01-01',
            'status' => 'IN_PROGRESS',
        ]);

        $this->task = Task::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'assigned_to' => $this->employee->id,
            'title' => 'Développement API REST',
            'status' => 'IN_PROGRESS',
        ]);
    }

    public function test_can_log_time_and_manager_can_approve(): void
    {
        // 1. Saisie de temps par le collaborateur
        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/timesheets/log', [
                'project_id' => $this->project->id,
                'task_id' => $this->task->id,
                'date' => Carbon::now()->toDateString(),
                'hours' => 7.5,
                'description' => 'Conception et tests automatisés des endpoints API.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'SUBMITTED')
            ->assertJsonPath('data.hours', '7.50');

        $entryId = $response->json('data.id');

        // 2. Approbation par le chef de projet
        $reviewResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson("/api/v1/timesheets/{$entryId}/review", [
                'action' => 'APPROVE',
            ]);

        $reviewResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('time_entries', [
            'id' => $entryId,
            'status' => 'APPROVED',
            'approved_by' => $this->managerUser->id,
        ]);
    }

    public function test_can_reject_timesheet_with_reason(): void
    {
        $entry = TimeEntry::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'employee_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'date' => Carbon::now()->toDateString(),
            'hours' => 8.0,
            'description' => 'Travail non documenté.',
            'status' => 'SUBMITTED',
        ]);

        $rejectResponse = $this->actingAs($this->managerUser, 'sanctum')
            ->postJson("/api/v1/timesheets/{$entry->id}/review", [
                'action' => 'REJECT',
                'reason' => 'Veuillez préciser la tâche associée.',
            ]);

        $rejectResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.rejection_reason', 'Veuillez préciser la tâche associée.');
    }
}

