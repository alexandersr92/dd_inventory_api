<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\NotificationEvent;
use App\Events\ClientRegistered;
use App\Events\BoxClosed;
use App\Events\UserCreated;
use App\Notifications\DynamicSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar el estado de los inquilinos
        \App\Services\TenantManager::clear();

        // Llenar el catálogo de eventos para poder testear
        $this->seed(\Database\Seeders\NotificationEventsSeeder::class);
    }

    /**
     * Test que valida la obtención de la matriz de configuración de notificaciones.
     */
    public function test_get_notification_settings_catalog(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        Sanctum::actingAs($owner);

        // Consultar el endpoint
        $response = $this->getJson('/api/v1/notifications/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'settings',
            'users'
        ]);

        // Debe retornar las notificaciones de ámbito tenant, por ejemplo tenant.box_closed y tenant.user_created
        $response->assertJsonFragment(['key' => 'tenant.box_closed']);
        $response->assertJsonFragment(['key' => 'tenant.user_created']);
    }

    /**
     * Test que valida la actualización de las preferencias de notificación de un Tenant.
     */
    public function test_update_notification_settings(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        $recipientUser = User::factory()->create(['organization_id' => $org->id]);

        Sanctum::actingAs($owner);

        // Guardar configuración personalizada
        $response = $this->putJson('/api/v1/notifications/settings/tenant.box_closed', [
            'value' => 'enabled',
            'channels' => ['mail'],
            'recipients' => [
                'user_ids' => [$recipientUser->id],
                'emails' => ['supervisor@externo.com']
            ]
        ]);

        $response->assertStatus(200);

        // Verificar persistencia en base de datos
        $this->assertDatabaseHas('settings', [
            'organization_id' => $org->id,
            'type' => 'notification_preference',
            'key' => 'tenant.box_closed',
            'value' => 'enabled'
        ]);
    }


    /**
     * Test de integración del Dispatcher de Notificaciones para un evento Global (ClientRegistered).
     */
    public function test_global_notification_event_dispatches_correctly(): void
    {
        Notification::fake();

        $org = Organization::factory()->create([
            'name' => 'Mega Client S.A.',
            'email' => 'welcome@megaclient.com'
        ]);

        // Disparar el evento global
        event(new ClientRegistered($org));

        // Debe enviarse la notificación a la dirección de correo indicada en el evento
        Notification::assertSentOnDemand(
            DynamicSystemNotification::class,
            function ($notification, $channels, $notifiable) use ($org) {
                return $notifiable->routes['mail'] === $org->email &&
                       $notification->eventKey === 'client.registered' &&
                       $notification->data['client_name'] === 'Mega Client S.A.';
            }
        );
    }


    /**
     * Test de integración del Dispatcher para evento de Tenant con destinatarios dinámicos.
     */
    public function test_tenant_notification_event_respects_custom_recipients(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        $recipientUser = User::factory()->create(['organization_id' => $org->id]);

        // Simular que el cliente configuró destinatarios personalizados
        Setting::create([
            'organization_id' => $org->id,
            'type' => 'notification_preference',
            'key' => 'tenant.box_closed',
            'value' => 'enabled',
            'options' => [
                'channels' => ['mail'],
                'recipients' => [
                    'user_ids' => [$recipientUser->id],
                    'emails' => ['externo@test.com']
                ]
            ]
        ]);

        // Disparar el evento
        $boxData = ['name' => 'Caja Principal 01', 'user_name' => 'Juan Pérez', 'balance' => '500.00'];
        event(new BoxClosed($boxData, $org->id));

        // Se debió enviar al usuario interno configurado
        Notification::assertSentTo(
            $recipientUser,
            DynamicSystemNotification::class,
            function ($notification) {
                return $notification->eventKey === 'tenant.box_closed' &&
                       $notification->data['box_name'] === 'Caja Principal 01';
            }
        );

        // Se debió enviar al correo externo configurado
        Notification::assertSentOnDemand(
            DynamicSystemNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'externo@test.com' &&
                       $notification->eventKey === 'tenant.box_closed';
            }
        );

        // NO se debió enviar al propietario original (owner) ya que el tenant especificó una lista restrictiva
        Notification::assertNotSentTo($owner, DynamicSystemNotification::class);
    }


    /**
     * Test que valida que no se envíe nada si la notificación está deshabilitada por el cliente.
     */
    public function test_tenant_notification_ignored_when_disabled(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $org = Organization::factory()->create([
            'tenancy_type' => 'shared',
            'owner_id' => $owner->id
        ]);
        $owner->update(['organization_id' => $org->id]);
        $this->setupTenantUser($owner, $org);

        // Simular que el cliente deshabilitó la notificación
        Setting::create([
            'organization_id' => $org->id,
            'type' => 'notification_preference',
            'key' => 'tenant.box_closed',
            'value' => 'disabled',
            'options' => [
                'channels' => ['mail'],
                'recipients' => [
                    'user_ids' => [$owner->id],
                    'emails' => []
                ]
            ]
        ]);

        // Disparar el evento
        $boxData = ['name' => 'Caja Principal 01', 'user_name' => 'Juan Pérez', 'balance' => '500.00'];
        event(new BoxClosed($boxData, $org->id));

        // No se debió enviar nada
        Notification::assertNothingSent();
    }
}
