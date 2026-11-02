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
        // 1. GABARITS DE BADGES IMPRIMABLES (Badge Templates)
        Schema::create('badge_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('name'); // ex: Badge Standard Employé, Badge Stagiaire, Badge Visiteur
            $table->string('orientation')->default('PORTRAIT'); // PORTRAIT, LANDSCAPE
            $table->integer('width_mm')->default(85); // Format standard CR80 (85.6mm)
            $table->integer('height_mm')->default(54); // Format standard CR80 (53.98mm)
            $table->string('background_image_path')->nullable();
            $table->json('layout_config')->nullable(); // Positions des calques : Photo, Nom, Matricule, QR Code
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        // 2. BADGES ÉMIS (Badges)
        Schema::create('badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('badge_template_id')->nullable()->index();
            $table->string('badge_number')->unique(); // ex: BDG-NTM-2026-0001
            $table->string('security_token', 64)->unique(); // Jeton sécurisé pour contrôle d'accès
            $table->string('status')->default('ACTIVE'); // ACTIVE, SUSPENDED, REVOKED, EXPIRED
            $table->string('qr_code_path')->nullable(); // Rendu statique du QR d'identification
            $table->string('printable_pdf_path')->nullable(); // PDF HD généré pour impression
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->uuid('revoked_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('badge_template_id')->references('id')->on('badge_templates')->onDelete('set null');
            $table->foreign('revoked_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. ÉVÉNEMENTS DU CYCLE DE VIE DES BADGES (Badge Events)
        Schema::create('badge_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('badge_id')->index();
            $table->string('action'); // ISSUED, PRINTED, SCANNED, SUSPENDED, REVOKED, REPLACED
            $table->uuid('performed_by')->nullable()->index();
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('badge_id')->references('id')->on('badges')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_events');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('badge_templates');
    }
};

