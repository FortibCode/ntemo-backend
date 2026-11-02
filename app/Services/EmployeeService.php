<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeService
{
    /**
     * Génère un matricule incrémental unique par organisation et par année.
     * Format : NTM-AAAA-0001
     */
    public function generateMatricule(Organization $organization): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = "NTM-{$year}-";

        $latestEmployee = Employee::where('organization_id', $organization->id)
            ->where('matricule', 'like', "{$prefix}%")
            ->orderBy('matricule', 'desc')
            ->first();

        if (!$latestEmployee) {
            return "{$prefix}0001";
        }

        $lastNumber = (int) substr($latestEmployee->matricule, -4);
        $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}{$nextNumber}";
    }

    /**
     * Crée un collaborateur au sein d'une transaction avec enregistrement d'affectation initiale.
     */
    public function createEmployee(Organization $organization, array $data): Employee
    {
        return DB::transaction(function () use ($organization, $data) {
            $matricule = $this->generateMatricule($organization);

            $employee = Employee::create(array_merge($data, [
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'matricule' => $matricule,
            ]));

            // Historisation de l'affectation initiale
            EmployeeAssignment::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'manager_id' => $employee->manager_id,
                'start_date' => $employee->hire_date,
                'reason' => 'EMBAUCHE',
                'notes' => 'Affectation initiale lors de la création du dossier.',
            ]);

            return $employee->fresh(['department', 'position', 'manager']);
        });
    }

    /**
     * Met à jour le collaborateur et historise les changements de poste ou de département.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $hasStructureChange = (
                (isset($data['department_id']) && $data['department_id'] !== $employee->department_id) ||
                (isset($data['position_id']) && $data['position_id'] !== $employee->position_id) ||
                (isset($data['manager_id']) && $data['manager_id'] !== $employee->manager_id)
            );

            $employee->update($data);

            if ($hasStructureChange) {
                // Fermer l'affectation précédente
                EmployeeAssignment::where('employee_id', $employee->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => Carbon::now()->toDateString()]);

                // Créer la nouvelle affectation
                EmployeeAssignment::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $employee->organization_id,
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'position_id' => $employee->position_id,
                    'manager_id' => $employee->manager_id,
                    'start_date' => Carbon::now()->toDateString(),
                    'reason' => 'MUTATION',
                    'notes' => 'Changement d\'affectation enregistré.',
                ]);
            }

            return $employee->fresh(['department', 'position', 'manager']);
        });
    }
}

