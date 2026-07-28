<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemDatabaseNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->payload['title'] ?? 'HealthLink notification',
            'body' => $this->payload['body'] ?? '',
            'level' => $this->payload['level'] ?? 'info',
            'category' => $this->payload['category'] ?? 'general',
            'icon' => $this->payload['icon'] ?? 'notifications-outline',
            'action_url' => $this->payload['action_url'] ?? null,
            'action_label' => $this->payload['action_label'] ?? null,
            'sender_name' => $this->payload['sender_name'] ?? null,
            'metadata' => $this->payload['metadata'] ?? [],
        ];
    }
}
