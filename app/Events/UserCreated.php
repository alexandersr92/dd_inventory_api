<?php

namespace App\Events;

use App\Models\User;

class UserCreated
{
    /**
     * @var User
     */
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification definition.
     */
    public function toNotification(): array
    {
        return [
            'event_key' => 'tenant.user_created',
            'organization_id' => $this->user->organization_id,
            'notifiables' => [], // Vacío por defecto; se resolverá dinámicamente desde settings
            'data' => [
                'user_name' => $this->user->name,
                'user_email' => $this->user->email,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]
        ];
    }
}
