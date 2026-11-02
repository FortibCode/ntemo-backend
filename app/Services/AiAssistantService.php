<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiDocumentTask;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiAssistantService
{
    /**
     * Traite un prompt utilisateur et génère un brouillon documentaire ou une réponse d'assistance RH.
     */
    public function generateDocumentDraft(
        User $user,
        string $taskType,
        string $prompt,
        array $context = []
    ): AiDocumentTask {
        return DB::transaction(function () use ($user, $taskType, $prompt, $context) {
            $draft = $this->synthesizeDraft($taskType, $prompt, $context);

            return AiDocumentTask::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'task_type' => $taskType,
                'status' => 'COMPLETED',
                'user_prompt' => $prompt,
                'generated_draft' => $draft,
                'was_accepted_by_user' => false,
            ]);
        });
    }

    /**
     * Envoie un message dans un fil de discussion IA avec réponse générée.
     */
    public function sendMessage(AiConversation $conversation, string $userMessage): array
    {
        return DB::transaction(function () use ($conversation, $userMessage) {
            // 1. Message utilisateur
            $userMsg = AiMessage::create([
                'id' => (string) Str::uuid(),
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $userMessage,
            ]);

            // 2. Synthèse de réponse d'assistance professionnelle
            $replyContent = $this->generateAssistantResponse($conversation, $userMessage);

            $assistantMsg = AiMessage::create([
                'id' => (string) Str::uuid(),
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $replyContent,
                'tokens_usage' => [
                    'prompt_tokens' => strlen($userMessage) / 4,
                    'completion_tokens' => strlen($replyContent) / 4,
                ],
            ]);

            return [
                'user_message' => $userMsg,
                'assistant_message' => $assistantMsg,
            ];
        });
    }

    protected function synthesizeDraft(string $taskType, string $prompt, array $context): string
    {
        $orgName = $context['organization_name'] ?? 'NTEMO Consulting';

        return match ($taskType) {
            'DRAFT_JOB_DESCRIPTION' => "FICHE DE POSTE — {$prompt}\n\n1. CONTEXTE ET ENTREPRISE\n{$orgName} recrute dans le cadre de l'expansion de ses activités de conseil et d'ingénierie.\n\n2. MISSIONS PRINCIPALES\n- Assurer le pilotage opérationnel des projets assignés.\n- Garantir la conformité et la rigueur d'exécution technique.\n- Collaborer étroitement avec les équipes transverses.\n\n3. PROFIL RECHERCHÉ\n- Formation supérieure Bac+5 en Ingénierie ou Gestion.\n- Rigueur, adaptabilité et sens du service client.",
            'DRAFT_CONTRACT_CLAUSE' => "CLAUSE SPÉCIFIQUE — {$prompt}\nLe collaborateur s'engage expressément à préserver la stricte confidentialité des données, codes sources et informations stratégiques de {$orgName} pendant toute la durée de son contrat et après sa rupture pour quelque motif que ce soit.",
            default => "SYNTHÈSE DOCUMENTAIRE ASSISTÉE PAR IA\nPrompt : {$prompt}\nContenu rédigé selon les standards de {$orgName}."
        };
    }

    protected function generateAssistantResponse(AiConversation $conversation, string $prompt): string
    {
        return "En tant qu'assistant documentaire NTEMO ONE, j'ai analysé votre demande (« {$prompt} »). Les modèles et gabarits administratifs de l'organisation restent à votre disposition pour formaliser et exporter ce document en PDF ou DOCX.";
    }
}

