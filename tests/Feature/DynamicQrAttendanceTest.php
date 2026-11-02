<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DynamicQrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected AttendanceDevice $device;
    protected User $userEmployee;
    protected Employee $employee;
    protected AttendanceService $attendanceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attendanceService = app(AttendanceService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Test Org',
            'slug' => 'ntemo-test',
            'is_active' => true,
        ]);

        $this->device = AttendanceDevice::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Borne Entrée Principale',
            'device_identifier' => 'KIOSK-ENTREE-01',
            'secret_key' => 'secret_hmac_key_test_12345',
            'type' => 'WALL_KIOSK',
            'is_active' => true,
        ]);

        $this->userEmployee = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Jean Collaborateur',
            'email' => 'jean@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'user_id' => $this->userEmployee->id,
            'matricule' => 'NTM-2026-0001',
            'first_name' => 'Jean',
            'last_name' => 'Collaborateur',
            'hire_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_can_generate_15s_dynamic_qr_and_punch_successfully(): void
    {
        // 1. Générer le QR code pour la borne
        $qrData = $this->attendanceService->generateDynamicQr($this->device);

        $this->assertEquals(15, $qrData['validity_seconds']);
        $this->assertNotEmpty($qrData['token']);

        // 2. Scan et pointage par l'employé
        $response = $this->actingAs($this->userEmployee, 'sanctum')
            ->postJson('/api/v1/attendance/punch', [
                'token' => $qrData['token'],
                'type' => 'IN',
                'latitude' => -4.2634,
                'longitude' => 15.2429,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'IN');

        // Vérification de la création de l'événement brut
        $this->assertDatabaseHas('attendance_events', [
            'organization_id' => $this->org->id,
            'employee_id' => $this->employee->id,
            'type' => 'IN',
            'source' => 'DYNAMIC_QR',
        ]);

        // Vérification de la synthèse du jour
        $this->assertDatabaseHas('attendance_days', [
            'organization_id' => $this->org->id,
            'employee_id' => $this->employee->id,
            'status' => 'PRESENT',
        ]);
    }

    public function test_anti_replay_protection_rejects_reusing_the_same_qr_token(): void
    {
        $qrData = $this->attendanceService->generateDynamicQr($this->device);

        // Premier pointage réussi
        $this->actingAs($this->userEmployee, 'sanctum')
            ->postJson('/api/v1/attendance/punch', [
                'token' => $qrData['token'],
                'type' => 'IN',
            ])->assertStatus(201);

        // Deuxième tentative avec le même token (doit échouer)
        $replayResponse = $this->actingAs($this->userEmployee, 'sanctum')
            ->postJson('/api/v1/attendance/punch', [
                'token' => $qrData['token'],
                'type' => 'IN',
            ]);

        $replayResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_expired_qr_code_after_15_seconds_is_rejected(): void
    {
        $qrData = $this->attendanceService->generateDynamicQr($this->device);

        // Avancer le temps au-delà des 15 secondes
        Carbon::setTestNow(Carbon::now()->addSeconds(16));

        $response = $this->actingAs($this->userEmployee, 'sanctum')
            ->postJson('/api/v1/attendance/punch', [
                'token' => $qrData['token'],
                'type' => 'IN',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Le QR code a expiré. Veuillez rescanner le code dynamique en cours.',
            ]);

        Carbon::setTestNow(); // Reset
    }
}
