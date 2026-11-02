<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiAssistantService $aiService
    ) {}

    /**
     * Démarrer ou lister les conversations de l'utilisateur.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = AiConversation::where('user_id', $user->id)
            ->with('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    /**
     * Créer une nouvelle session de discussion.
     */
    public function createConversation(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'context_module' => ['sometimes', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $conv = AiConversation::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'title' => $request->title,
            'context_module' => $request->input('context_module', 'documents'),
        ]);

        return response()->json(['success' => true, 'data' => $conv], Response::HTTP_CREATED);
    }

    /**
     * Envoyer un prompt dans une conversation.
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $conversation = AiConversation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $result = $this->aiService->sendMessage($conversation, $request->message);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Déclencher la rédaction assistée d'un document ou d'une clause.
     */
    public function generateDraft(Request $request): JsonResponse
    {
        $request->validate([
            'task_type' => ['required', 'string', 'in:DRAFT_JOB_DESCRIPTION,DRAFT_CONTRACT_CLAUSE,SUMMARIZE_REPORT'],
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $task = $this->aiService->generateDocumentDraft(
            $user,
            $request->task_type,
            $request->prompt,
            ['organization_name' => $user->organization?->name]
        );

        return response()->json([
            'success' => true,
            'message' => 'Brouillon généré par l\'assistant IA.',
            'data' => $task,
        ], Response::HTTP_CREATED);
    }
}

