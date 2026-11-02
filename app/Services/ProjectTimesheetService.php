<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProjectTimesheetService
{
    /**
     * Enregistre une ligne de feuille de temps (timesheet) pour un collaborateur.
     */
    public function logTime(
        Employee $employee,
        Project $project,
        Carbon $date,
        float $hours,
        string $description,
        ?Task $task = null
    ): TimeEntry {
        if ($hours <= 0 || $hours > 24) {
            throw new InvalidArgumentException("Le nombre d'heures doit être compris entre 0.25 et 24.");
        }

        return DB::transaction(function () use ($employee, $project, $date, $hours, $description, $task) {
            return TimeEntry::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'date' => $date->toDateString(),
                'hours' => $hours,
                'description' => $description,
                'status' => 'SUBMITTED',
            ]);
        });
    }

    /**
     * Approbation d'une feuille de temps par le chef de projet ou le manager.
     */
    public function approveTimeEntry(TimeEntry $entry, User $approver): TimeEntry
    {
        if ($entry->status === 'APPROVED') {
            throw new InvalidArgumentException("Cette saisie est déjà approuvée.");
        }

        $entry->update([
            'status' => 'APPROVED',
            'approved_by' => $approver->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
        ]);

        return $entry;
    }

    /**
     * Rejet argumenté d'une saisie de temps.
     */
    public function rejectTimeEntry(TimeEntry $entry, User $approver, string $reason): TimeEntry
    {
        $entry->update([
            'status' => 'REJECTED',
            'approved_by' => $approver->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        return $entry;
    }
}

