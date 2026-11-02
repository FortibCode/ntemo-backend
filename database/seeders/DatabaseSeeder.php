<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Création de l'Organisation Principale
        $organization = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Consulting',
            'slug' => 'ntemo-consulting',
            'registration_number' => 'CG-BZV-01-2026-B12-0042',
            'tax_number' => 'M2026001234F',
            'email' => 'contact@ntemoconsulting.com',
            'phone' => '+242 06 000 0000',
            'country' => 'Congo',
            'city' => 'Brazzaville',
            'timezone' => 'Africa/Brazzaville',
            'currency' => 'XAF',
            'is_active' => true,
        ]);

        // 2. Création des Permissions Fondamentales
        $permissionsList = [
            // Employés & Organisation
            ['name' => 'Voir tous les employés', 'slug' => 'employees.view_all', 'module' => 'employees'],
            ['name' => 'Voir son équipe directe', 'slug' => 'employees.view_team', 'module' => 'employees'],
            ['name' => 'Voir son propre profil', 'slug' => 'employees.view_self', 'module' => 'employees'],
            ['name' => 'Créer et modifier des employés', 'slug' => 'employees.create_edit', 'module' => 'employees'],
            ['name' => 'Archiver / Supprimer des employés', 'slug' => 'employees.delete', 'module' => 'employees'],

            // Structure (Départements / Postes)
            ['name' => 'Gérer les départements', 'slug' => 'departments.manage', 'module' => 'structure'],
            ['name' => 'Gérer les postes', 'slug' => 'positions.manage', 'module' => 'structure'],

            // Contrats & Documents
            ['name' => 'Gérer les contrats de travail', 'slug' => 'contracts.manage', 'module' => 'contracts'],
            ['name' => 'Voir les documents confidentiels', 'slug' => 'documents.read_confidential', 'module' => 'documents'],
            ['name' => 'Téléverser des documents RH', 'slug' => 'documents.upload', 'module' => 'documents'],

            // Présences & Pointage
            ['name' => 'Pointage personnel QR', 'slug' => 'attendance.point_self', 'module' => 'attendance'],
            ['name' => 'Scanner terminal de pointage', 'slug' => 'attendance.scan_device', 'module' => 'attendance'],
            ['name' => 'Voir les présences de l\'équipe', 'slug' => 'attendance.view_team', 'module' => 'attendance'],
            ['name' => 'Gérer toutes les présences', 'slug' => 'attendance.manage_all', 'module' => 'attendance'],
            ['name' => 'Demander régularisation présence', 'slug' => 'attendance.request_correction', 'module' => 'attendance'],
            ['name' => 'Approuver régularisation présence', 'slug' => 'attendance.approve_correction', 'module' => 'attendance'],

            // Congés
            ['name' => 'Demander un congé', 'slug' => 'leaves.request_self', 'module' => 'leaves'],
            ['name' => 'Approbation N+1 congés', 'slug' => 'leaves.approve_n1', 'module' => 'leaves'],
            ['name' => 'Validation finale RH congés', 'slug' => 'leaves.approve_final_rh', 'module' => 'leaves'],

            // Audit
            ['name' => 'Consulter les journaux d\'audit', 'slug' => 'audit.view', 'module' => 'audit'],
        ];

        $permissionModels = [];
        foreach ($permissionsList as $permData) {
            $permissionModels[$permData['slug']] = Permission::create([
                'id' => (string) Str::uuid(),
                'name' => $permData['name'],
                'slug' => $permData['slug'],
                'module' => $permData['module'],
            ]);
        }

        // 3. Création des Rôles Spécifiques
        $roleSuperAdmin = Role::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Administrateur',
            'slug' => 'super_admin',
            'description' => 'Administration système globale',
            'is_system' => true,
        ]);

        $roleOrgAdmin = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Directeur RH / Admin Organisation',
            'slug' => 'org_admin',
            'description' => 'Gestion globale de l\'organisation et des RH',
            'is_system' => false,
        ]);

        $roleManager = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Responsable Hiérarchique N+1',
            'slug' => 'department_manager',
            'description' => 'Supervision de l\'équipe, approbations N+1',
            'is_system' => false,
        ]);

        $roleEmployee = Role::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Employé Collaborateur',
            'slug' => 'employee',
            'description' => 'Accès espace personnel, pointage, congés',
            'is_system' => false,
        ]);

        // 4. Attribution des Permissions aux Rôles
        $allPermissions = Permission::all();
        $roleSuperAdmin->permissions()->sync($allPermissions->pluck('id'));
        $roleOrgAdmin->permissions()->sync($allPermissions->pluck('id'));

        $managerPerms = collect([
            $permissionModels['employees.view_self']->id,
            $permissionModels['employees.view_team']->id,
            $permissionModels['attendance.point_self']->id,
            $permissionModels['attendance.view_team']->id,
            $permissionModels['attendance.approve_correction']->id,
            $permissionModels['leaves.request_self']->id,
            $permissionModels['leaves.approve_n1']->id,
        ]);
        $roleManager->permissions()->sync($managerPerms);

        $employeePerms = collect([
            $permissionModels['employees.view_self']->id,
            $permissionModels['attendance.point_self']->id,
            $permissionModels['attendance.request_correction']->id,
            $permissionModels['leaves.request_self']->id,
        ]);
        $roleEmployee->permissions()->sync($employeePerms);

        // 5. Création de l'Administrateur Initial
        $adminUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Administrateur NTEMO',
            'email' => 'admin@ntemo.local',
            'password' => Hash::make('NtemoAdmin2026!'),
            'is_active' => true,
        ]);
        $adminUser->roles()->attach([$roleSuperAdmin->id, $roleOrgAdmin->id]);
    }
}
