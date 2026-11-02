<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveService $leaveService
    ) {}

    /**
     * Liste des types de congés paramétrés pour l'organisation.
     */
    public function types(Request $request): JsonResponse
    {
        $types = LeaveType::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

    /**
     * Mes demandes de congés (Collaborateur connecté).
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['data' => []]);
        }

        $requests = LeaveRequest::where('employee_id', $employee->id)
            ->with(['leaveType', 'approvals.approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($requests);
    }

    /**
     * Soumission d'une nouvelle demande de congé.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun profil employé rattaché à ce compte.',
            ], Response::HTTP_FORBIDDEN);
        }

        $leaveType = LeaveType::where('id', $request->leave_type_id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        try {
            $leaveRequest = $this->leaveService->submitLeaveRequest(
                $employee,
                $leaveType,
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Demande de congé déposée avec succès.',
                'data' => $leaveRequest,
            ], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Décision N+1 (Manager d'équipe).
     */
    public function reviewByManager(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'approved' => ['required', 'boolean'],
            'comments' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        if (!$user->hasPermission('leaves.approve_n1')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour valider au niveau hiérarchique N+1.',
            ], Response::HTTP_FORBIDDEN);
        }

        $leaveRequest = LeaveRequest::where('id', $id)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $updated = $this->leaveService->reviewByManager(
            $leaveRequest,
            $user,
            $request->boolean('approved'),
            $request->comments
        );

        return response()->json([
            'success' => true,
            'message' => 'Décision N+1 enregistrée avec succès.',
            'data' => $updated,
        ]);
    }

    /**
     * Décision finale RH.
     */
    public function reviewByHr(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'approved' => ['required', 'boolean'],
            'comments' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        if (!$user->hasPermission('leaves.approve_final_rh')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour la validation finale RH.',
            ], Response::HTTP_FORBIDDEN);
        }

        $leaveRequest = LeaveRequest::where('id', $id)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $updated = $this->leaveService->reviewByHr(
            $leaveRequest,
            $user,
            $request->boolean('approved'),
            $request->comments
        );

        return response()->json([
            'success' => true,
            'message' => 'Décision finale RH enregistrée avec succès.',
            'data' => $updated,
        ]);
    }
}

