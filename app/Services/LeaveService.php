<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LeaveService
{
    /**
     * Calcule le nombre de jours ouvrés entre deux dates (hors samedi/dimanche).
     */
    public function calculateBusinessDays(Carbon $startDate, Carbon $endDate): float
    {
        if ($startDate->isAfter($endDate)) {
            throw new InvalidArgumentException("La date de fin doit être postérieure à la date de début.");
        }

        $days = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Lundi (1) -> Vendredi (5)
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return (float) $days;
    }

    /**
     * Soumet une nouvelle demande de congé avec réservation du solde en attente.
     */
    public function submitLeaveRequest(
        Employee $employee,
        LeaveType $leaveType,
        Carbon $startDate,
        Carbon $endDate,
        string $reason
    ): LeaveRequest {
        $totalDays = $this->calculateBusinessDays($startDate, $endDate);
        if ($totalDays <= 0) {
            throw new InvalidArgumentException("La durée de la demande doit être d'au moins 1 jour ouvré.");
        }

        return DB::transaction(function () use ($employee, $leaveType, $startDate, $endDate, $totalDays, $reason) {
            $year = $startDate->year;

            // Vérifier le solde si le type de congé est rémunéré / plafonné
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($balance && $balance->remaining_days < $totalDays) {
                throw new InvalidArgumentException(
                    "Solde insuffisant pour ce type de congé (disponible : {$balance->remaining_days} j, demandé : {$totalDays} j)."
                );
            }

            // Déterminer le statut initial selon le workflow requis
            $initialStatus = $leaveType->requires_manager_approval ? 'PENDING_N1' : 'PENDING_HR';

            $request = LeaveRequest::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_days' => $totalDays,
                'reason' => $reason,
                'status' => $initialStatus,
            ]);

            // Mettre à jour le solde en attente
            if ($balance) {
                $balance->increment('pending_days', $totalDays);
                $balance->decrement('remaining_days', $totalDays);
            }

            return $request->fresh(['employee', 'leaveType']);
        });
    }

    /**
     * Validation ou rejet par le manager N+1.
     */
    public function reviewByManager(LeaveRequest $request, User $manager, bool $approved, ?string $comments = null): LeaveRequest
    {
        if ($request->status !== 'PENDING_N1') {
            throw new InvalidArgumentException("Cette demande n'est pas en attente de validation N+1.");
        }

        return DB::transaction(function () use ($request, $manager, $approved, $comments) {
            LeaveApproval::create([
                'id' => (string) Str::uuid(),
                'leave_request_id' => $request->id,
                'approver_id' => $manager->id,
                'level' => 'MANAGER_N1',
                'decision' => $approved ? 'APPROVED' : 'REJECTED',
                'comments' => $comments,
                'decided_at' => Carbon::now(),
            ]);

            if ($approved) {
                $nextStatus = $request->leaveType->requires_hr_approval ? 'PENDING_HR' : 'APPROVED';
                $request->update(['status' => $nextStatus]);
            } else {
                $request->update(['status' => 'REJECTED']);
                // Restituer le solde
                $this->restorePendingBalance($request);
            }

            return $request->fresh(['approvals']);
        });
    }

    /**
     * Validation ou rejet final par le Directeur RH.
     */
    public function reviewByHr(LeaveRequest $request, User $hrUser, bool $approved, ?string $comments = null): LeaveRequest
    {
        if ($request->status !== 'PENDING_HR') {
            throw new InvalidArgumentException("Cette demande n'est pas en attente de validation finale RH.");
        }

        return DB::transaction(function () use ($request, $hrUser, $approved, $comments) {
            LeaveApproval::create([
                'id' => (string) Str::uuid(),
                'leave_request_id' => $request->id,
                'approver_id' => $hrUser->id,
                'level' => 'HR_DIRECTOR',
                'decision' => $approved ? 'APPROVED' : 'REJECTED',
                'comments' => $comments,
                'decided_at' => Carbon::now(),
            ]);

            if ($approved) {
                $request->update(['status' => 'APPROVED']);

                // Débiter définitivement du solde utilisé
                $balance = LeaveBalance::where('employee_id', $request->employee_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $request->start_date->year)
                    ->first();

                if ($balance) {
                    $balance->decrement('pending_days', $request->total_days);
                    $balance->increment('used_days', $request->total_days);
                }
            } else {
                $request->update(['status' => 'REJECTED']);
                $this->restorePendingBalance($request);
            }

            return $request->fresh(['approvals']);
        });
    }

    protected function restorePendingBalance(LeaveRequest $request): void
    {
        $balance = LeaveBalance::where('employee_id', $request->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $request->start_date->year)
            ->first();

        if ($balance) {
            $balance->decrement('pending_days', $request->total_days);
            $balance->increment('remaining_days', $request->total_days);
        }
    }
}

