<?php

namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\QrSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AttendanceService
{
    public const QR_TTL_SECONDS = 15; // Exigence stricte de 15s

    /**
     * Génère un QR token dynamique à validité éphémère de 15 secondes pour un terminal.
     */
    public function generateDynamicQr(AttendanceDevice $device): array
    {
        if (!$device->is_active) {
            throw new InvalidArgumentException("Le terminal de pointage est inactif ou révoqué.");
        }

        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds(self::QR_TTL_SECONDS);
        $nonce = Str::random(32);

        // Payload cryptographique : device_id|nonce|timestamp_expiration
        $payload = "{$device->id}|{$nonce}|{$expiresAt->timestamp}";
        $signature = hash_hmac('sha256', $payload, $device->secret_key);

        $qrSession = QrSession::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'nonce' => $nonce,
            'token_signature' => $signature,
            'issued_at' => $now,
            'expires_at' => $expiresAt,
            'is_consumed' => false,
        ]);

        return [
            'token' => base64_encode("{$payload}|{$signature}"),
            'expires_at' => $expiresAt->toISOString(),
            'validity_seconds' => self::QR_TTL_SECONDS,
            'qr_session_id' => $qrSession->id,
        ];
    }

    /**
     * Valide le scan du QR code dynamique et enregistre le pointage brut immutable.
     */
    public function recordPunch(
        Employee $employee,
        string $rawToken,
        string $type = 'IN',
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $deviceMeta = null
    ): AttendanceEvent {
        $decoded = base64_decode($rawToken, true);
        if (!$decoded) {
            throw new InvalidArgumentException("Format du jeton de pointage invalide.");
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            throw new InvalidArgumentException("Structure du jeton altérée.");
        }

        [$deviceId, $nonce, $expiresTimestamp, $signature] = $parts;

        return DB::transaction(function () use (
            $employee,
            $deviceId,
            $nonce,
            $expiresTimestamp,
            $signature,
            $type,
            $latitude,
            $longitude,
            $deviceMeta
        ) {
            $device = AttendanceDevice::where('id', $deviceId)
                ->where('organization_id', $employee->organization_id)
                ->first();

            if (!$device || !$device->is_active) {
                throw new InvalidArgumentException("Terminal de pointage inconnu ou inactif.");
            }

            // Vérification de la signature HMAC
            $payload = "{$deviceId}|{$nonce}|{$expiresTimestamp}";
            $expectedSignature = hash_hmac('sha256', $payload, $device->secret_key);

            if (!hash_equals($expectedSignature, $signature)) {
                throw new InvalidArgumentException("Signature de sécurité du QR code falsifiée.");
            }

            // Vérification de l'expiration stricte de 15s
            $now = Carbon::now();
            if ($now->timestamp > (int) $expiresTimestamp) {
                throw new InvalidArgumentException("Le QR code a expiré. Veuillez rescanner le code dynamique en cours.");
            }

            // Vérification anti-rejeu et consommation atomique
            $qrSession = QrSession::where('nonce', $nonce)
                ->where('device_id', $device->id)
                ->lockForUpdate()
                ->first();

            if (!$qrSession || $qrSession->is_consumed) {
                throw new InvalidArgumentException("Ce QR code a déjà été utilisé pour un pointage (anti-rejeu).");
            }

            $qrSession->update(['is_consumed' => true]);

            // Enregistrement de l'événement brut immutable
            $event = AttendanceEvent::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'work_site_id' => $device->work_site_id,
                'qr_session_id' => $qrSession->id,
                'recorded_at' => $now,
                'type' => $type,
                'source' => 'DYNAMIC_QR',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'device_meta' => $deviceMeta,
                'is_verified' => true,
            ]);

            // Mise à jour de la synthèse du jour
            $this->updateDailyAttendance($employee, $event);

            return $event;
        });
    }

    /**
     * Calcule et met à jour la synthèse de la journée sans jamais altérer les événements bruts.
     */
    protected function updateDailyAttendance(Employee $employee, AttendanceEvent $event): void
    {
        $today = $event->recorded_at->toDateString();

        $dayRecord = AttendanceDay::firstOrCreate(
            [
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'date' => $today,
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => 'PRESENT',
            ]
        );

        if ($event->type === 'IN' && (!$dayRecord->first_check_in || $event->recorded_at->isBefore($dayRecord->first_check_in))) {
            $dayRecord->first_check_in = $event->recorded_at;
        }

        if ($event->type === 'OUT' && (!$dayRecord->last_check_out || $event->recorded_at->isAfter($dayRecord->last_check_out))) {
            $dayRecord->last_check_out = $event->recorded_at;
        }

        // Calcul du temps travaillé si entrée et sortie présentes
        if ($dayRecord->first_check_in && $dayRecord->last_check_out) {
            $diffMinutes = $dayRecord->first_check_in->diffInMinutes($dayRecord->last_check_out);
            $dayRecord->worked_minutes = max(0, $diffMinutes);
        }

        $dayRecord->save();
    }
}

