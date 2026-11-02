<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * Liste filtrée et paginée des employés du tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Employee::where('organization_id', $user->organization_id)
            ->with(['department', 'position', 'manager']);

        // Si l'utilisateur a uniquement le droit d'équipe
        if (!$user->hasPermission('employees.view_all') && $user->hasPermission('employees.view_team')) {
            $userEmployee = $user->employee;
            if ($userEmployee) {
                $query->where('manager_id', $userEmployee->id);
            } else {
                return response()->json(['data' => []]);
            }
        } elseif (!$user->hasPermission('employees.view_all') && !$user->hasPermission('employees.view_team')) {
            // Uniquement soi-même
            $userEmployee = $user->employee;
            if ($userEmployee) {
                $query->where('id', $userEmployee->id);
            } else {
                return response()->json(['data' => []]);
            }
        }

        // Filtres de recherche
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('matricule', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $employees = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return EmployeeResource::collection($employees)->response();
    }

    /**
     * Création d'un nouvel employé.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $organization = $request->user()->organization;

        $employee = $this->employeeService->createEmployee(
            $organization,
            $request->validated()
        );

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Détail d'un employé avec vérification d'isolation tenant.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('organization_id', $user->organization_id)
            ->with(['department', 'position', 'manager', 'contracts', 'documents', 'assignments'])
            ->findOrFail($id);

        // Vérification des autorisations
        if (!$user->hasPermission('employees.view_all')) {
            $userEmployee = $user->employee;
            $isSelf = $userEmployee && $userEmployee->id === $employee->id;
            $isDirectReport = $userEmployee && $employee->manager_id === $userEmployee->id && $user->hasPermission('employees.view_team');

            if (!$isSelf && !$isDirectReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit au dossier de cet employé.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return (new EmployeeResource($employee))->response();
    }

    /**
     * Mise à jour d'un employé.
     */
    public function update(UpdateEmployeeRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('organization_id', $user->organization_id)->findOrFail($id);

        $updatedEmployee = $this->employeeService->updateEmployee($employee, $request->validated());

        return (new EmployeeResource($updatedEmployee))->response();
    }

    /**
     * Archivage logique (Soft Delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('employees.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour archiver cet employé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $employee = Employee::where('organization_id', $user->organization_id)->findOrFail($id);
        $employee->update(['status' => 'TERMINATED', 'termination_date' => now()->toDateString()]);
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dossier employé archivé avec succès.',
        ]);
    }
}

