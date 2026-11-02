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
        // 1. NOTIFICATIONS IN-APP
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('user_id')->index(); // Destinataire principal
            $table->string('type'); // leave_requested, leave_approved, attendance_anomaly, badge_issued, contract_expiring
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Métadonnées contextuelles (lien d'action, ID ressource, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'read_at']);
        });

        // 2. PRÉFÉRENCES DE NOTIFICATION UTILISATEUR (Notification Preferences)
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('channel'); // EMAIL, PUSH_MOBILE, IN_APP, SMS
            $table->string('notification_type'); // leaves, attendance, badges, contracts, system
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'channel', 'notification_type']);
        });

        // 3. HISTORIQUE D'EXPÉDITION MULTI-CANAL (Notification Deliveries)
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->index();
            $table->string('channel'); // EMAIL, PUSH_MOBILE, SMS
            $table->string('status')->default('SENT'); // PENDING, SENT, FAILED
            $table->text('error_details')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};

