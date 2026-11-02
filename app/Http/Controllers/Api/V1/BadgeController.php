<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Employee;
use App\Services\BadgeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BadgeController extends Controller
{
    public function __construct(
        protected BadgeService $badgeService
    ) {}

    /**
     * Obtenir le badge virtuel actif de l'employé connecté (Mobile / Web).
     */
    public function myVirtualBadge(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun dossier collaborateur associé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $badge = Badge::where('employee_id', $employee->id)
            ->where('status', 'ACTIVE')
            ->with(['employee.department', 'employee.position', 'template'])
            ->latest('created_at')
            ->first();

        if (!$badge) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun badge actif émis pour votre profil.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $badge->id,
                'badge_number' => $badge->badge_number,
                'security_token' => $badge->security_token,
                'status' => $badge->status,
                'issued_at' => $badge->issued_at->toDateString(),
                'expires_at' => $badge->expires_at?->toDateString(),
                'employee' => [
                    'matricule' => $employee->matricule,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->title,
                    'photo_path' => $employee->photo_path,
                ],
            ],
        ]);
    }

    /**
     * Émission d'un badge par un gestionnaire RH.
     */
    public function issue(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $user = $request->user();
        if (!$user->hasPermission('badges.generate_revoke')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour émettre des badges.',
            ], Response::HTTP_FORBIDDEN);
        }

        $employee = Employee::where('id', $request->employee_id)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $expiresAt = $request->expires_at ? Carbon::parse($request->expires_at) : null;
        $badge = $this->badgeService->issueBadge($employee, null, $user, $expiresAt);

        return response()->json([
            'success' => true,
            'message' => 'Badge émis avec succès.',
            'data' => $badge,
        ], Response::HTTP_CREATED);
    }

    /**
     * Révocation d'un badge par un gestionnaire RH.
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if (!$user->hasPermission('badges.generate_revoke')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour révoquer des badges.',
            ], Response::HTTP_FORBIDDEN);
        }

        $badge = Badge::where('id', $id)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $revokedBadge = $this->badgeService->revokeBadge($badge, $request->reason, $user);

        return response()->json([
            'success' => true,
            'message' => 'Badge révoqué avec succès.',
            'data' => $revokedBadge,
        ]);
    }

    /**
     * Vérification de sécurité lors du scan (pour agents de sécurité ou bornes).
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'security_token' => ['required', 'string'],
        ]);

        $result = $this->badgeService->verifyBadgeSecurityToken(
            $request->security_token,
            $request->user()->organization_id
        );

        return response()->json($result, $result['is_valid'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

