<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentGeneration;
use App\Models\Employee;
use App\Services\DocumentEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentEngineController extends Controller
{
    public function __construct(
        protected DocumentEngineService $documentEngineService
    ) {}

    /**
     * Liste des modèles documentaires disponibles.
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = DocumentTemplate::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->with(['category', 'currentVersion'])
            ->get();

        return response()->json(['success' => true, 'data' => $templates]);
    }

    /**
     * Déclenche la génération d'un document pour un collaborateur.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => ['required', 'uuid', 'exists:document_templates,id'],
            'employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'extra_variables' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        if (!$user->hasPermission('docs.generate')) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour générer des documents administratifs.',
            ], Response::HTTP_FORBIDDEN);
        }

        $template = DocumentTemplate::where('id', $request->template_id)
            ->where('organization_id', $user->organization_id)
            ->with('currentVersion')
            ->firstOrFail();

        if (!$template->currentVersion) {
            return response()->json([
                'success' => false,
                'message' => 'Ce modèle ne possède aucune version active.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $employee = null;
        if ($request->employee_id) {
            $employee = Employee::where('id', $request->employee_id)
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();
        }

        $generation = $this->documentEngineService->generateDocument(
            $template->currentVersion,
            $user,
            $employee,
            $request->input('extra_variables', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Document généré avec succès.',
            'data' => $generation,
        ], Response::HTTP_CREATED);
    }

    /**
     * Téléchargement sécurisé du document généré avec contrôle strict des accès.
     */
    public function download(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $generation = DocumentGeneration::where('id', $id)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        if ($generation->status !== 'COMPLETED' || !$generation->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier n\'est pas encore prêt ou a échoué.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Contrôle d'accès : RH, demandeur ou employé concerné
        $isRequester = $generation->requested_by === $user->id;
        $isTargetEmployee = $user->employee && $generation->employee_id === $user->employee->id;
        $hasDocPermission = $user->hasPermission('docs.generate') || $user->hasPermission('documents.read_confidential');

        if (!$isRequester && !$isTargetEmployee && !$hasDocPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Accès confidentiel interdit à ce document.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!Storage::disk('local')->exists($generation->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Le fichier physique est introuvable sur le stockage sécurisé.',
            ], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('local')->download(
            $generation->file_path,
            "{$generation->title}." . strtolower($generation->output_format)
        );
    }
}

