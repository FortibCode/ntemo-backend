<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\ProjectTimesheetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectTimesheetController extends Controller
{
    public function __construct(
        protected ProjectTimesheetService $timesheetService
    ) {}

    /**
     * Liste des projets de l'organisation.
     */
    public function projects(Request $request): JsonResponse
    {
        $projects = Project::where('organization_id', $request->user()->organization_id)
            ->with(['projectManager', 'tasks'])
            ->get();

        return response()->json(['success' => true, 'data' => $projects]);
    }

    /**
     * Mes saisies de temps (Collaborateur connecté).
     */
    public function myTimeEntries(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['data' => []]);
        }

        $entries = TimeEntry::where('employee_id', $employee->id)
            ->with(['project', 'task', 'approver'])
            ->orderBy('date', 'desc')
            ->paginate(30);

        return response()->json($entries);
    }

    /**
     * Déclaration d'heures sur un projet/tâche.
     */
    public function storeTimeEntry(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun profil employé associé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $project = Project::where('id', $request->project_id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $task = null;
        if ($request->task_id) {
            $task = Task::where('id', $request->task_id)
                ->where('project_id', $project->id)
                ->firstOrFail();
        }

        $entry = $this->timesheetService->logTime(
            $employee,
            $project,
            Carbon::parse($request->date),
            (float) $request->hours,
            $request->description,
            $task
        );

        return response()->json([
            'success' => true,
            'message' => 'Heures déclarées avec succès.',
            'data' => $entry,
        ], Response::HTTP_CREATED);
    }

    /**
     * Approbation / Rejet d'une saisie de temps par le responsable.
     */
    public function reviewTimeEntry(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:APPROVE,REJECT'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = TimeEntry::where('id', $id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        if ($request->action === 'APPROVE') {
            $updated = $this->timesheetService->approveTimeEntry($entry, $request->user());
            $msg = 'Feuille de temps approuvée.';
        } else {
            $request->validate(['reason' => ['required', 'string']]);
            $updated = $this->timesheetService->rejectTimeEntry($entry, $request->user(), $request->reason);
            $msg = 'Feuille de temps rejetée.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => $updated,
        ]);
    }
}

