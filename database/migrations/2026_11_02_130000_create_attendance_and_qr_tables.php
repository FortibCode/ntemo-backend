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
        // 1. SITES DE TRAVAIL (Work Sites)
        Schema::create('work_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('name'); // ex: Siège Social Brazzaville, Agence Pointe-Noire
            $table->string('code')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('radius_meters')->default(150); // Périmètre de tolérance geofencing
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'code']);
        });

        // 2. HORAIRES DE TRAVAIL (Work Schedules)
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->string('name'); // ex: 40h Standard NTEMO, Horaires Gardiennage, etc.
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->integer('grace_period_minutes')->default(15); // Tolérance de retard avant marquage
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        // 3. JOURS DU PLANNING (Work Schedule Days)
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_schedule_id')->index();
            $table->unsignedTinyInteger('day_of_week'); // 1 (Lundi) -> 7 (Dimanche)
            $table->time('start_time')->nullable(); // ex: 08:00:00
            $table->time('end_time')->nullable();   // ex: 17:00:00
            $table->time('break_start_time')->nullable(); // ex: 12:30:00
            $table->time('break_end_time')->nullable();   // ex: 13:30:00
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();

            $table->foreign('work_schedule_id')->references('id')->on('work_schedules')->onDelete('cascade');
            $table->unique(['work_schedule_id', 'day_of_week']);
        });

        // 4. AFFECTATION D'UN PLANNING À UN EMPLOYÉ
        Schema::create('employee_work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('work_schedule_id')->index();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('work_schedule_id')->references('id')->on('work_schedules')->onDelete('cascade');
        });

        // 5. TERMINAUX DE POINTAGE AGRÉÉS (Attendance Devices)
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('work_site_id')->nullable()->index();
            $table->string('name'); // ex: Tablette Accueil Batiment A
            $table->string('device_identifier')->unique(); // Identifiant matériel / Empreinte unique
            $table->string('secret_key'); // Clé secrète HMAC pour signature des QR codes
            $table->string('type')->default('WALL_KIOSK'); // WALL_KIOSK, TABLET, MOBILE_SCANNER
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('work_site_id')->references('id')->on('work_sites')->onDelete('set null');
        });

        // 6. SESSIONS QR DYNAMIQUES (QR Sessions - Validité 15s)
        Schema::create('qr_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('device_id')->index();
            $table->string('nonce', 64)->unique();
            $table->string('token_signature'); // Signature HMAC-SHA256
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index(); // Stricte validité de 15 secondes
            $table->boolean('is_consumed')->default(false); // Anti-rejeu
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('attendance_devices')->onDelete('cascade');
        });

        // 7. ÉVÉNEMENTS BRUTS DE POINTAGE (Attendance Events - IMMUTABLES)
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('device_id')->nullable()->index();
            $table->uuid('work_site_id')->nullable()->index();
            $table->uuid('qr_session_id')->nullable()->index();
            $table->timestamp('recorded_at'); // Horodatage serveur absolu avec fuseau horaire
            $table->string('type'); // IN (Arrivée), OUT (Sortie), BREAK_IN, BREAK_OUT
            $table->string('source')->default('DYNAMIC_QR'); // DYNAMIC_QR, MANUAL_OVERRIDE, NFC, BIOMETRIC
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('device_meta')->nullable(); // User agent, OS version, modèle
            $table->boolean('is_verified')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('attendance_devices')->onDelete('set null');
            $table->foreign('work_site_id')->references('id')->on('work_sites')->onDelete('set null');
            $table->foreign('qr_session_id')->references('id')->on('qr_sessions')->onDelete('set null');
            $table->index(['employee_id', 'recorded_at']);
        });

        // 8. SYNTHÈSES JOURNALIÈRES DE PRÉSENCE (Attendance Days)
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->date('date');
            $table->timestamp('first_check_in')->nullable();
            $table->timestamp('last_check_out')->nullable();
            $table->integer('scheduled_minutes')->default(0);
            $table->integer('worked_minutes')->default(0);
            $table->integer('break_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_departure_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->string('status')->default('PRESENT'); // PRESENT, LATE, ABSENT, ON_LEAVE, ANOMALY, REST_DAY
            $table->boolean('has_anomaly')->default(false);
            $table->string('anomaly_reason')->nullable();
            $table->boolean('is_locked')->default(false); // Clôturé après paie
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'date']);
            $table->index(['organization_id', 'date']);
        });

        // 9. DEMANDES DE RÉGULARISATION DE PRÉSENCE (Attendance Corrections)
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('employee_id')->index();
            $table->date('date');
            $table->string('requested_event_type'); // IN, OUT
            $table->time('requested_time');
            $table->text('reason'); // Motif obligatoire
            $table->string('status')->default('PENDING'); // PENDING, APPROVED_N1, APPROVED_RH, REJECTED
            $table->uuid('reviewed_by')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('qr_sessions');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('work_schedule_days');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('work_sites');
    }
};

