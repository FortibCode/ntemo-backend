<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $user;
    protected AiAssistantService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAssistantService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO AI Org',
            'slug' => 'ntemo-ai',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Expert RH',
            'email' => 'expert_rh@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_can_generate_document_draft_with_ai(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ai/tasks/draft', [
                'task_type' => 'DRAFT_JOB_DESCRIPTION',
                'prompt' => 'Chef de Projet Informatique Senior',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.task_type', 'DRAFT_JOB_DESCRIPTION')
            ->assertJsonPath('data.status', 'COMPLETED');

        $this->assertDatabaseHas('ai_document_tasks', [
            'user_id' => $this->user->id,
            'task_type' => 'DRAFT_JOB_DESCRIPTION',
        ]);
    }

    public function test_can_send_message_in_ai_conversation(): void
    {
        $conv = AiConversation::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'title' => 'Assistance RH',
            'context_module' => 'documents',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/ai/conversations/{$conv->id}/messages", [
                'message' => 'Comment formuler une clause de dédit-formation ?',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.assistant_message.role', 'assistant');

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conv->id,
            'role' => 'assistant',
        ]);
    }
}

