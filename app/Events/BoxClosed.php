<?php

namespace App\Events;

class BoxClosed
{
    /**
     * @var array
     */
    public $boxData;

    /**
     * @var string
     */
    public $organizationId;

    /**
     * Create a new event instance.
     */
    public function __construct(array $boxData, string $organizationId)
    {
        $this->boxData = $boxData;
        $this->organizationId = $organizationId;
    }

    /**
     * Get the notification definition.
     */
    public function toNotification(): array
    {
        return [
            'event_key' => 'tenant.box_closed',
            'organization_id' => $this->organizationId,
            'notifiables' => [], // Vacío por defecto; se resolverá dinámicamente desde settings
            'data' => [
                'box_name' => $this->boxData['name'] ?? 'Caja General',
                'closed_by' => $this->boxData['user_name'] ?? 'Usuario',
                'balance' => $this->boxData['balance'] ?? '0.00',
                'closed_at' => now()->format('Y-m-d H:i:s'),
            ]
        ];
    }
}
