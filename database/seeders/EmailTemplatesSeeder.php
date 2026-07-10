<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'client_welcome',
                'name' => 'Bienvenida a Nuevos Clientes',
                'subject' => '¡Bienvenido a DipleBill, {client_name}!',
                'body' => '<p>Hola <strong>{owner_name}</strong>,</p>
<p>Queremos darte la más cordial bienvenida a <strong>DipleBill</strong>. Tu cuenta organizacional para <strong>{client_name}</strong> ha sido creada con éxito.</p>
<div style="background-color: #f3f4f6; border-radius: 8px; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px; color: #374151;"><strong>Credenciales de acceso:</strong></p>
    <p style="margin: 8px 0 0 0; font-size: 14px; color: #4b5563;">Correo: {owner_email}</p>
    <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b5563;">Contraseña: La que estableciste durante el registro.</p>
</div>
<p>Puedes iniciar sesión en tu panel de administración ingresando al siguiente enlace:</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{login_url}" style="background-color: #6366f1; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);">Ingresar a la Plataforma</a>
</p>
<p>Si tienes alguna duda o necesitas soporte durante la configuración inicial de tu inventario o sucursales, no dudes en ponerte en contacto con nuestro equipo.</p>
<p>Atentamente,<br>El equipo de DipleBill</p>',
                'variables' => [
                    'client_name' => 'Nombre de la empresa cliente',
                    'owner_name' => 'Nombre del usuario propietario',
                    'owner_email' => 'Correo electrónico de acceso',
                    'login_url' => 'Enlace de acceso a la plataforma'
                ]
            ],
            [
                'key' => 'tenant.user_created',
                'name' => 'Bienvenida a Usuario del Sistema',
                'subject' => '¡Bienvenido a DipleBill, {user_name}!',
                'body' => '<p>Hola <strong>{user_name}</strong>,</p>
<p>Tu cuenta de usuario ha sido creada con éxito en el sistema DipleBill.</p>
<div style="background-color: #f3f4f6; border-radius: 8px; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px; color: #374151;"><strong>Credenciales de acceso:</strong></p>
    <p style="margin: 8px 0 0 0; font-size: 14px; color: #4b5563;">Correo: {user_email}</p>
    <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b5563;">Contraseña: La que te fue asignada por tu administrador.</p>
</div>
<p>Por motivos de seguridad, te recomendamos cambiar tu contraseña una vez que ingreses al sistema por primera vez.</p>
<p>Atentamente,<br>El equipo de DipleBill</p>',
                'variables' => [
                    'user_name' => 'Nombre del usuario',
                    'user_email' => 'Correo electrónico de acceso',
                    'created_at' => 'Fecha de creación de la cuenta'
                ]
            ],
            [
                'key' => 'invoice_notification',
                'name' => 'Notificación de Factura Emitida',
                'subject' => 'Factura Emitida - {invoice_number} de {client_name}',
                'body' => '<p>Estimado Cliente,</p>
<p>Le informamos que se ha generado un nuevo comprobante electrónico de venta a su nombre por parte de <strong>{client_name}</strong>.</p>
<div style="background-color: #f3f4f6; border-radius: 8px; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px; color: #374151;"><strong>Detalles del Comprobante:</strong></p>
    <p style="margin: 8px 0 0 0; font-size: 14px; color: #4b5563;">Folio: {invoice_number}</p>
    <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b5563;">Fecha de Emisión: {issue_date}</p>
    <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b5563;">Monto Total: <strong>{invoice_amount}</strong></p>
</div>
<p>Puede consultar y descargar los archivos PDF y XML de su factura haciendo clic en el siguiente botón:</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{download_url}" style="background-color: #10B981; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);">Ver y Descargar Factura</a>
</p>
<p>Agradecemos su preferencia.</p>
<p>Atentamente,<br><strong>{client_name}</strong></p>',
                'variables' => [
                    'client_name' => 'Nombre de la empresa que emite la factura',
                    'invoice_number' => 'Folio o número de la factura',
                    'invoice_amount' => 'Monto total facturado',
                    'issue_date' => 'Fecha de emisión',
                    'download_url' => 'Enlace de descarga del PDF/XML de la factura'
                ]
            ],
            [
                'key' => 'payment_reminder',
                'name' => 'Recordatorio de Pago de Crédito',
                'subject' => 'Recordatorio de Pago - Saldo Pendiente con {client_name}',
                'body' => '<p>Hola <strong>{customer_name}</strong>,</p>
<p>Te escribimos de parte de <strong>{client_name}</strong> para recordarte que tienes un saldo pendiente por liquidar en tu línea de crédito.</p>
<div style="background-color: #f3f4f6; border-radius: 8px; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px; color: #374151;"><strong>Resumen de tu cuenta:</strong></p>
    <p style="margin: 8px 0 0 0; font-size: 14px; color: #4b5563;">Saldo Pendiente: <strong style="color: #ef4444;">{pending_amount}</strong></p>
    <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b5563;">Fecha Límite de Pago: {due_date}</p>
    <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b5563;">Límite de Crédito: {credit_limit}</p>
</div>
<p>Te invitamos a acercarte a tu sucursal o realizar tu abono a la brevedad para mantener activa tu línea de crédito y evitar cargos adicionales.</p>
<p>Si ya realizaste tu pago, por favor haz caso omiso a este correo electrónico.</p>
<p>Atentamente,<br><strong>{client_name}</strong></p>',
                'variables' => [
                    'client_name' => 'Nombre del comercio o empresa acreedora',
                    'customer_name' => 'Nombre del cliente deudor',
                    'pending_amount' => 'Monto del saldo vencido o pendiente',
                    'credit_limit' => 'Límite de crédito asignado',
                    'due_date' => 'Fecha de vencimiento del pago'
                ]
            ],
        ];

        foreach ($templates as $tpl) {
            \App\Models\EmailTemplate::updateOrCreate(
                ['key' => $tpl['key']],
                [
                    'name' => $tpl['name'],
                    'subject' => $tpl['subject'],
                    'body' => $tpl['body'],
                    'variables' => $tpl['variables'],
                ]
            );
        }
    }
}
