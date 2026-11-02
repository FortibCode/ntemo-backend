<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Crée et diffuse une notification selon les préférences du destinataire.
     */
    public function send(
        User $recipient,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): AppNotification {
        return DB::transaction(function () use ($recipient, $type, $title, $message, $data) {
            $notification = AppNotification::create([
                'id' => (string) Str::uuid(),
                'organization_id' => $recipient->organization_id,
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            // Vérifier si le canal IN_APP est activé (par défaut true)
            NotificationDelivery::create([
                'id' => (string) Str::uuid(),
                'notification_id' => $notification->id,
                'channel' => 'IN_APP',
                'status' => 'SENT',
                'delivered_at' => now(),
            ]);

            // Vérifier les canaux secondaires (ex: EMAIL)
            $emailPref = NotificationPreference::where('user_id', $recipient->id)
                ->where('channel', 'EMAIL')
                ->where('notification_type', $type)
                ->first();

            if (!$emailPref || $emailPref->is_enabled) {
                NotificationDelivery::create([
                    'id' => (string) Str::uuid(),
                    'notification_id' => $notification->id,
                    'channel' => 'EMAIL',
                    'status' => 'SENT',
                    'delivered_at' => now(),
                ]);
            }

            return $notification;
        });
    }

    /**
     * Marque toutes les notifications non lues d'un utilisateur comme lues.
     */
    public function markAllAsRead(User $user): int
    {
        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}

