<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\BadgeEvent;
use App\Models\BadgeTemplate;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BadgeService
{
    /**
     * Émet un badge numérique officiel pour un employé (avec révocation automatique du précédent).
     */
    public function issueBadge(
        Employee $employee,
        ?BadgeTemplate $template = null,
        ?User $issuer = null,
        ?Carbon $expiresAt = null
    ): Badge {
        return DB::transaction(function () use ($employee, $template, $issuer, $expiresAt) {
            // Révoquer les anciens badges actifs de l'employé
            $activeBadges = Badge::where('employee_id', $employee->id)
                ->where('status', 'ACTIVE')
                ->get();

            foreach ($activeBadges as $oldBadge) {
                $oldBadge->update([
                    'status' => 'REPLACED',
                    'revoked_at' => Carbon::now(),
                    'revocation_reason' => 'Remplacé par la réémission d\'un nouveau badge.',
                    'revoked_by' => $issuer?->id,
                ]);

                BadgeEvent::create([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $employee->organization_id,
                    'badge_id' => $oldBadge->id,
                    'action' => 'REPLACED',
                    'performed_by' => $issuer?->id,
                    'details' => 'Badge automatiquement remplacé.',
                ]);
            }

            // Génération du numéro de badge unique
            $badgeNumber = 'BDG-' . str_replace('-', '', $employee->matricule) . '-' . strtoupper(Str::random(4));
            $securityToken = hash('sha256', Str::random(40) . $employee->id . Carbon::now()->timestamp);

            $badge = Badge::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'badge_template_id' => $template?->id,
                'badge_number' => $badgeNumber,
                'security_token' => $securityToken,
                'status' => 'ACTIVE',
                'issued_at' => Carbon::now()->toDateString(),
                'expires_at' => $expiresAt ? $expiresAt->toDateString() : null,
            ]);

            BadgeEvent::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $employee->organization_id,
                'badge_id' => $badge->id,
                'action' => 'ISSUED',
                'performed_by' => $issuer?->id,
                'details' => 'Badge initialement émis.',
            ]);

            return $badge->fresh(['employee', 'template']);
        });
    }

    /**
     * Révoque un badge existant.
     */
    public function revokeBadge(Badge $badge, string $reason, ?User $revoker = null): Badge
    {
        if ($badge->status === 'REVOKED') {
            throw new InvalidArgumentException("Ce badge est déjà révoqué.");
        }

        return DB::transaction(function () use ($badge, $reason, $revoker) {
            $badge->update([
                'status' => 'REVOKED',
                'revoked_at' => Carbon::now(),
                'revocation_reason' => $reason,
                'revoked_by' => $revoker?->id,
            ]);

            BadgeEvent::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $badge->organization_id,
                'badge_id' => $badge->id,
                'action' => 'REVOKED',
                'performed_by' => $revoker?->id,
                'details' => "Motif : {$reason}",
            ]);

            return $badge;
        });
    }

    /**
     * Vérification de sécurité lors du scan du badge par un agent de contrôle.
     */
    public function verifyBadgeSecurityToken(string $securityToken, string $organizationId): array
    {
        $badge = Badge::where('security_token', $securityToken)
            ->where('organization_id', $organizationId)
            ->with(['employee.department', 'employee.position'])
            ->first();

        if (!$badge) {
            return [
                'is_valid' => false,
                'message' => 'Badge introuvable ou non reconnu par cette organisation.',
            ];
        }

        if (!$badge->isActive()) {
            return [
                'is_valid' => false,
                'badge_number' => $badge->badge_number,
                'status' => $badge->status,
                'message' => "Ce badge est inactif (statut : {$badge->status}). Accès refusé.",
            ];
        }

        return [
            'is_valid' => true,
            'badge_number' => $badge->badge_number,
            'employee' => [
                'id' => $badge->employee->id,
                'matricule' => $badge->employee->matricule,
                'full_name' => $badge->employee->full_name,
                'department' => $badge->employee->department?->name,
                'position' => $badge->employee->position?->title,
                'photo_path' => $badge->employee->photo_path,
            ],
            'issued_at' => $badge->issued_at->toDateString(),
            'expires_at' => $badge->expires_at?->toDateString(),
        ];
    }
}

