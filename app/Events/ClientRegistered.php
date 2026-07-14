<?php

namespace App\Events;

use App\Models\Organization;

class ClientRegistered
{
    /**
     * @var Organization
     */
    public $organization;

    /**
     * Create a new event instance.
     */
    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
    }

    /**
     * Get the notification definition.
     */
    public function toNotification(): array
    {
        return [
            'event_key' => 'client.registered',
            'organization_id' => null, // Ámbito Global (No pertenece a un Tenant específico de cara al envío global)
            'notifiables' => [
                $this->organization->email,
            ],
            'data' => [
                'client_name' => $this->organization->name,
                'client_email' => $this->organization->email,
            ]
        ];
    }
}
