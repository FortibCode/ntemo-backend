<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $hrUser;
    protected Employee $employee;
    protected DocumentTemplate $template;
    protected DocumentEngineService $documentEngineService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->documentEngineService = app(DocumentEngineService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Docs Org',
            'slug' => 'ntemo-docs',
            'is_active' => true,
        ]);

        $permDoc = Permission::create([
            'id' => (string) Str::uuid(),
            'name' => 'Generate Docs',
            'slug' => 'docs.generate',
            'module' => 'documents',
        ]);

        $roleHr = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'HR Role',
            'slug' => 'hr_role',
        ]);
        $roleHr->permissions()->attach($permDoc->id);

        $this->hrUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'RH Document Manager',
            'email' => 'doc_manager@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->hrUser->roles()->attach($roleHr->id);

        $this->employee = Employee::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'matricule' => 'NTM-2026-0044',
            'first_name' => 'Sylvie',
            'last_name' => 'Makosso',
            'hire_date' => '2026-01-15',
            'status' => 'ACTIVE',
        ]);

        $category = DocumentCategory::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Attestations',
            'slug' => 'attestations',
        ]);

        $this->template = DocumentTemplate::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'document_category_id' => $category->id,
            'title' => 'Attestation de Travail',
            'code' => 'ATTEST_TRAVAIL',
            'output_format' => 'PDF',
            'is_active' => true,
        ]);

        // Création de la version v1.0 avec balises
        $this->documentEngineService->createTemplateVersion(
            $this->template,
            "ATTESTATION DE TRAVAIL\nNous certifions que Mme/M. {{employee.full_name}}, matricule {{employee.matricule}}, est employé(e) au sein de {{organization.name}} depuis le {{employee.hire_date}}.",
            'v1.0',
            $this->hrUser,
            'Version initiale officielle'
        );
    }

    public function test_can_generate_document_with_merged_variables_and_sha256_hash(): void
    {
        $response = $this->actingAs($this->hrUser, 'sanctum')
            ->postJson('/api/v1/documents/generate', [
                'template_id' => $this->template->id,
                'employee_id' => $this->employee->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'COMPLETED');

        $docId = $response->json('data.id');
        $sha256 = $response->json('data.file_hash_sha256');

        $this->assertNotEmpty($sha256);
        $this->assertEquals(64, strlen($sha256));

        // Vérification de la persistance en base
        $this->assertDatabaseHas('document_generations', [
            'id' => $docId,
            'organization_id' => $this->org->id,
            'status' => 'COMPLETED',
        ]);

        // Vérification du fichier physique dans le disque privé
        $filePath = $response->json('data.file_path');
        Storage::disk('local')->assertExists($filePath);

        $savedContent = Storage::disk('local')->get($filePath);
        $this->assertStringContainsString('Sylvie Makosso', $savedContent);
        $this->assertStringContainsString('NTM-2026-0044', $savedContent);
        $this->assertStringContainsString('NTEMO Docs Org', $savedContent);
    }

    public function test_unauthorized_user_cannot_generate_documents(): void
    {
        $simpleUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Employé Sans Droit Docs',
            'email' => 'simple@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($simpleUser, 'sanctum')
            ->postJson('/api/v1/documents/generate', [
                'template_id' => $this->template->id,
                'employee_id' => $this->employee->id,
            ]);

        $response->assertStatus(403);
    }
}

