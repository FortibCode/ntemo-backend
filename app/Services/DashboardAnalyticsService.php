<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * Calcule les métriques RH et opérationnelles globales pour la Direction et les RH.
     */
    public function getExecutiveKpis(Organization $organization): array
    {
        $today = Carbon::now()->toDateString();
        $orgId = $organization->id;

        // 1. Effectifs
        $totalEmployees = Employee::where('organization_id', $orgId)->count();
        $activeEmployees = Employee::where('organization_id', $orgId)->where('status', 'ACTIVE')->count();

        // 2. Présences du jour
        $presentToday = AttendanceDay::where('organization_id', $orgId)
            ->where('date', $today)
            ->whereIn('status', ['PRESENT', 'LATE'])
            ->count();

        $lateToday = AttendanceDay::where('organization_id', $orgId)
            ->where('date', $today)
            ->where('status', 'LATE')
            ->count();

        $attendanceRate = $activeEmployees > 0
            ? round(($presentToday / $activeEmployees) * 100, 1)
            : 0.0;

        // 3. Congés
        $pendingLeaves = LeaveRequest::where('organization_id', $orgId)
            ->whereIn('status', ['PENDING_N1', 'PENDING_HR'])
            ->count();

        $onLeaveToday = LeaveRequest::where('organization_id', $orgId)
            ->where('status', 'APPROVED')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        // 4. Projets & Heures
        $activeProjects = Project::where('organization_id', $orgId)
            ->where('status', 'IN_PROGRESS')
            ->count();

        $monthlyHours = TimeEntry::where('organization_id', $orgId)
            ->whereYear('date', Carbon::now()->year)
            ->whereMonth('date', Carbon::now()->month)
            ->where('status', 'APPROVED')
            ->sum('hours');

        return [
            'date' => $today,
            'workforce' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'on_leave_today' => $onLeaveToday,
            ],
            'attendance' => [
                'present_today' => $presentToday,
                'late_today' => $lateToday,
                'attendance_rate_percentage' => $attendanceRate,
            ],
            'leaves' => [
                'pending_approval_count' => $pendingLeaves,
            ],
            'projects' => [
                'active_projects_count' => $activeProjects,
                'approved_hours_this_month' => (float) $monthlyHours,
            ],
        ];
    }
}

