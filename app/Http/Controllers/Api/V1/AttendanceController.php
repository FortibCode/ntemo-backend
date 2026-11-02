<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\AttendanceDay;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Génération d'une session QR Code dynamique pour une borne ou un terminal agréé.
     */
    public function generateQrSession(Request $request): JsonResponse
    {
        $request->validate([
            'device_identifier' => ['required', 'string'],
        ]);

        $device = AttendanceDevice::where('device_identifier', $request->device_identifier)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $qrData = $this->attendanceService->generateDynamicQr($device);

        return response()->json([
            'success' => true,
            'data' => $qrData,
        ]);
    }

    /**
     * Scan et validation de pointage par un collaborateur (Mobile / Web).
     */
    public function punch(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'type' => ['sometimes', 'in:IN,OUT,BREAK_IN,BREAK_OUT'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'device_meta' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun dossier collaborateur associé à ce compte.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $event = $this->attendanceService->recordPunch(
                $employee,
                $request->token,
                $request->input('type', 'IN'),
                $request->latitude ? (float) $request->latitude : null,
                $request->longitude ? (float) $request->longitude : null,
                $request->device_meta
            );

            return response()->json([
                'success' => true,
                'message' => 'Pointage enregistré avec succès.',
                'data' => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'recorded_at' => $event->recorded_at->toISOString(),
                ],
            ], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Historique des présences de l'employé connecté.
     */
    public function myAttendances(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['data' => []]);
        }

        $records = AttendanceDay::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }
}

