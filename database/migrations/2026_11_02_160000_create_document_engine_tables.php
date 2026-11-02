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
        // 1. CATÉGORIES DOCUMENTAIRES (Document Categories)
        Schema::create('document_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('name'); // Attestations de travail, Certificats, Fiches de poste, Bilans
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'slug']);
        });

        // 2. MODÈLES DOCUMENTAIRES (Document Templates)
        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('document_category_id')->nullable()->index();
            $table->string('title'); // ex: Attestation de Travail Standard, Fiche de Poste Cadre
            $table->string('code')->nullable();
            $table->string('output_format')->default('PDF'); // PDF, DOCX, XLSX, PPTX
            $table->text('description')->nullable();
            $table->json('available_variables')->nullable(); // Liste documentée des balises supportées
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('document_category_id')->references('id')->on('document_categories')->onDelete('set null');
            $table->unique(['organization_id', 'code']);
        });

        // 3. VERSIONS DES MODÈLES (Document Template Versions - IMMUTABLES)
        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_template_id')->index();
            $table->string('version_number'); // v1.0, v1.1
            $table->longText('content'); // Contenu source ou structure balisée
            $table->string('file_template_path')->nullable(); // Fichier binaire si DOCX/XLSX/PPTX
            $table->string('changelog')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->foreign('document_template_id')->references('id')->on('document_templates')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['document_template_id', 'version_number']);
        });

        // 4. GÉNÉRATIONS DOCUMENTAIRES (Document Generations - Traçabilité & Archivage)
        Schema::create('document_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('document_template_version_id')->index();
            $table->uuid('employee_id')->nullable()->index(); // L'employé concerné par le document
            $table->uuid('requested_by')->index(); // L'utilisateur ayant sollicité la génération
            $table->string('title'); // Titre du document généré
            $table->string('output_format'); // PDF, DOCX, XLSX, PPTX
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED
            $table->string('file_path')->nullable(); // Emplacement de stockage sécurisé
            $table->string('file_hash_sha256', 64)->nullable(); // Empreinte d'intégrité
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->json('input_payload')->nullable(); // Données de fusion utilisées
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('document_template_version_id')->references('id')->on('document_template_versions')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_generations');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_categories');
    }
};

