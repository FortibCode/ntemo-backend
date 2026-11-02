<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. SESSIONS DE CONVERSATION AVEC L'ASSISTANT IA (AI Conversations)
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('user_id')->index();
            $table->string('title')->default('Nouvelle discussion');
            $table->string('context_module')->default('documents'); // documents, hr_legal, policy
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 2. MESSAGES ET RÉPONSES TRACÉES (AI Messages)
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_conversation_id')->index();
            $table->string('role'); // user, assistant, system
            $table->longText('content');
            $table->json('tokens_usage')->nullable(); // prompt_tokens, completion_tokens
            $table->json('suggested_actions')->nullable(); // Actions proposées (ex: créer un modèle)
            $table->timestamps();

            $table->foreign('ai_conversation_id')->references('id')->on('ai_conversations')->onDelete('cascade');
        });

        // 3. TÂCHES DOCUMENTAIRES ASSISTÉES PAR IA (AI Document Tasks)
        Schema::create('ai_document_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('user_id')->index();
            $table->string('task_type'); // DRAFT_JOB_DESCRIPTION, DRAFT_CONTRACT_CLAUSE, SUMMARIZE_REPORT
            $table->string('status')->default('COMPLETED'); // PENDING, PROCESSING, COMPLETED, FAILED
            $table->text('user_prompt');
            $table->longText('generated_draft');
            $table->boolean('was_accepted_by_user')->default(false);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_document_tasks');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};

