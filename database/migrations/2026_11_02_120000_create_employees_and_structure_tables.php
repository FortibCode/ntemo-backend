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
        // 1. DEPARTMENTS
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->uuid('manager_id')->nullable()->index(); // L'employé responsable du département
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'code']);
        });

        // Contrainte récursive après création de la table
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('departments')->onDelete('set null');
        });

        // 2. POSITIONS (Fonctions / Métiers)
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('department_id')->nullable()->index();
            $table->string('title');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->unique(['organization_id', 'code']);
        });

        // 3. EMPLOYEES
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('user_id')->nullable()->unique(); // Compte utilisateur d'accès web/mobile
            $table->string('matricule'); // ex: NTM-2026-0001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender')->nullable(); // M, F
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('nationality')->default('Congolaise');
            $table->string('marital_status')->nullable(); // SINGLE, MARRIED, DIVORCED, WIDOWED
            $table->integer('dependent_children')->default(0);

            // Coordonnées
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            // Identifiants administratifs & Sécurité sociale
            $table->string('national_id_number')->nullable(); // Numéro CNI / Passeport
            $table->string('social_security_number')->nullable(); // CNSS

            // Affectation actuelle
            $table->uuid('department_id')->nullable()->index();
            $table->uuid('position_id')->nullable()->index();
            $table->uuid('manager_id')->nullable()->index(); // Supérieur hiérarchique direct N+1

            // Données d'embauche
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE, ON_LEAVE, SUSPENDED, TERMINATED

            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');

            $table->unique(['organization_id', 'matricule']);
        });

        // Contrainte récursive et relation département manager
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
        });

        // 4. EMPLOYEE_ASSIGNMENTS (Historique des mutations et affectations)
        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('department_id')->nullable()->index();
            $table->uuid('position_id')->nullable()->index();
            $table->uuid('manager_id')->nullable()->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable(); // EMBOUCHE, PROMOTION, MUTATION
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
        });

        // 5. CONTRACTS
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->string('reference')->nullable(); // Référence administrative du contrat
            $table->string('type'); // CDI, CDD, STAGE, PRESTATION, APPRENTISSAGE
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->decimal('gross_salary', 14, 2)->nullable();
            $table->string('currency')->default('XAF');
            $table->string('status')->default('ACTIVE'); // DRAFT, ACTIVE, EXPIRED, TERMINATED
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['organization_id', 'reference']);
        });

        // 6. EMPLOYEE_DOCUMENTS (Pièces administratives et justificatives)
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->string('title');
            $table->string('category'); // CNI, PASSPORT, DIPLOMA, RESUME, MEDICAL, CONTRACT, OTHER
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->date('expiration_date')->nullable();
            $table->boolean('is_confidential')->default(true);
            $table->uuid('uploaded_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('employee_assignments');
        
        // Défaire la contrainte de clé étrangère departments -> employees
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
