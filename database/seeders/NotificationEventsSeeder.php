<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            // Ámbito GLOBAL: De Administrador a Cliente (Tenant)
            [
                'id' => 'client.registered',
                'scope' => 'global',
                'name' => 'Bienvenida a Cliente',
                'description' => 'Enviado al registrar un nuevo cliente/organización en la plataforma.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'billing.payment_pending',
                'scope' => 'global',
                'name' => 'Pago Pendiente',
                'description' => 'Enviado cuando se genera una factura de suscripción y está pendiente de pago.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'billing.payment_received',
                'scope' => 'global',
                'name' => 'Pago Recibido',
                'description' => 'Confirmación de pago de la suscripción mensual/anual de la organización.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'billing.payment_overdue',
                'scope' => 'global',
                'name' => 'Pago en Mora',
                'description' => 'Alerta enviada cuando el cliente se encuentra en mora con su suscripción.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'billing.expiring_soon',
                'scope' => 'global',
                'name' => 'Cuenta por Vencer',
                'description' => 'Alerta de aviso de vencimiento de cuenta que está próxima a expirar.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'module.purchased',
                'scope' => 'global',
                'name' => 'Adquisición de Módulo',
                'description' => 'Confirmación de que se ha activado o comprado un nuevo módulo del sistema.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'support.ticket_created',
                'scope' => 'global',
                'name' => 'Ticket de Soporte Registrado',
                'description' => 'Notificación de que se ha creado o recibido un ticket de soporte técnico.',
                'default_channels' => json_encode(['mail']),
            ],

            // Ámbito TENANT: Interno de la tienda a sus usuarios/destinatarios configurados
            [
                'id' => 'tenant.user_created',
                'scope' => 'tenant',
                'name' => 'Nuevo Usuario Creado',
                'description' => 'Enviado cuando se registra un nuevo usuario en la organización del tenant.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'tenant.box_closed',
                'scope' => 'tenant',
                'name' => 'Cierre de Caja',
                'description' => 'Notificación enviada cuando un cajero realiza el cierre de una caja registradora.',
                'default_channels' => json_encode(['mail']),
            ],
            [
                'id' => 'tenant.invoice_created',
                'scope' => 'tenant',
                'name' => 'Factura Creada',
                'description' => 'Configura reglas para recibir notificaciones al crear facturas (por monto, método de pago, etc.).',
                'default_channels' => json_encode(['mail']),
                'conditions_schema' => json_encode([
                    ['field' => 'grand_total', 'label' => 'Monto Total', 'type' => 'number'],
                    ['field' => 'payment_method', 'label' => 'Método de Pago', 'type' => 'text'],
                    ['field' => 'client_name', 'label' => 'Nombre del Cliente', 'type' => 'text'],
                    ['field' => 'invoice_type', 'label' => 'Tipo de Venta', 'type' => 'select', 'options' => ['cash', 'credit']],
                ]),
            ],
            [
                'id' => 'tenant.credit_created',
                'scope' => 'tenant',
                'name' => 'Crédito Registrado',
                'description' => 'Enviado cada vez que se registra una nueva venta a crédito.',
                'default_channels' => json_encode(['mail']),
            ],
        ];

        foreach ($events as $event) {
            DB::connection('central')->table('notification_events')->updateOrInsert(
                ['id' => $event['id']],
                [
                    'scope' => $event['scope'],
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'default_channels' => $event['default_channels'],
                    'conditions_schema' => $event['conditions_schema'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
