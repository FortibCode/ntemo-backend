<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $user;
    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(NotificationService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'NTEMO Notif Org',
            'slug' => 'ntemo-notif',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'name' => 'Utilisateur Cible',
            'email' => 'user_notif@ntemo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_can_receive_and_read_notification(): void
    {
        // 1. Envoi de notification via le service
        $notif = $this->notificationService->send(
            $this->user,
            'leave_approved',
            'Congé Approuvé',
            'Votre demande de congé pour Novembre 2026 a été validée par les RH.',
            ['request_id' => 'abc-123']
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
            'user_id' => $this->user->id,
            'type' => 'leave_approved',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notif->id,
            'channel' => 'IN_APP',
            'status' => 'SENT',
        ]);

        // 2. Consultation des notifications de l'utilisateur
        $listResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 1);

        // 3. Marquage individuel comme lue
        $readResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notif->id}/read");

        $readResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $notif->refresh();
        $this->assertNotNull($notif->read_at);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        // Créer 3 notifications non lues
        $this->notificationService->send($this->user, 'system', 'Alerte 1', 'Msg 1');
        $this->notificationService->send($this->user, 'system', 'Alerte 2', 'Msg 2');
        $this->notificationService->send($this->user, 'system', 'Alerte 3', 'Msg 3');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated_count', 3);

        $unreadRemaining = AppNotification::where('user_id', $this->user->id)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unreadRemaining);
    }
}

