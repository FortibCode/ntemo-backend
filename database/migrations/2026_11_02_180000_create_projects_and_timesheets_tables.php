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
        // 1. PROJETS (Projects)
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->uuid('project_manager_id')->nullable()->index(); // Responsable de projet
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('PLANNED'); // PLANNED, IN_PROGRESS, ON_HOLD, COMPLETED, CANCELLED
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('currency')->default('XAF');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('project_manager_id')->references('id')->on('employees')->onDelete('set null');
            $table->unique(['organization_id', 'code']);
        });

        // 2. MEMBRES D'UN PROJET (Project Members)
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('employee_id')->index();
            $table->string('role_in_project')->default('CONTRIBUTOR'); // LEAD, CONTRIBUTOR, REVIEWER, OBSERVER
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['project_id', 'employee_id']);
        });

        // 3. TÂCHES (Tasks)
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('project_id')->index();
            $table->uuid('assigned_to')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('MEDIUM'); // LOW, MEDIUM, HIGH, URGENT
            $table->string('status')->default('TODO'); // TODO, IN_PROGRESS, REVIEW, DONE
            $table->decimal('estimated_hours', 6, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('employees')->onDelete('set null');
        });

        // 4. SAISIES DES FEUILLES DE TEMPS (Time Entries / Timesheets)
        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('project_id')->index();
            $table->uuid('task_id')->nullable()->index();
            $table->date('date');
            $table->decimal('hours', 4, 2); // Nombre d'heures passées
            $table->text('description'); // Description de l'activité réalisée
            $table->string('status')->default('SUBMITTED'); // DRAFT, SUBMITTED, APPROVED, REJECTED
            $table->uuid('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};

