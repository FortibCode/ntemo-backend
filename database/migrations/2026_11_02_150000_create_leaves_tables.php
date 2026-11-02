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
        // 1. TYPES DE CONGÉS (Leave Types)
        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('name'); // Congé Payé Annuel, Maladie, Maternité, Paternité, Événement Familial, Sans Solde
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_days_per_year', 5, 2)->default(26.0); // 26 jours ouvrés par an selon le Code du travail congolais
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false); // Justificatif médical obligatoire, etc.
            $table->boolean('requires_manager_approval')->default(true); // Circuit N+1
            $table->boolean('requires_hr_approval')->default(true); // Circuit RH
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'code']);
        });

        // 2. SOLDES DE CONGÉS (Leave Balances)
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('leave_type_id')->index();
            $table->integer('year'); // Exercice annuel (ex: 2026)
            $table->decimal('allocated_days', 5, 2)->default(0);
            $table->decimal('used_days', 5, 2)->default(0);
            $table->decimal('pending_days', 5, 2)->default(0);
            $table->decimal('remaining_days', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        // 3. DEMANDES DE CONGÉS (Leave Requests)
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('leave_type_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 2);
            $table->text('reason'); // Motif de la demande
            $table->string('status')->default('PENDING_N1'); // PENDING_N1, PENDING_HR, APPROVED, REJECTED, CANCELLED
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
        });

        // 4. CIRCUIT D'APPROBATION (Leave Approvals)
        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('leave_request_id')->index();
            $table->uuid('approver_id')->index();
            $table->string('level'); // MANAGER_N1, HR_DIRECTOR
            $table->string('decision'); // APPROVED, REJECTED
            $table->text('comments')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 5. PIÈCES JOINTES (Leave Attachments)
        Schema::create('leave_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('leave_request_id')->index();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_attachments');
        Schema::dropIfExists('leave_approvals');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};

