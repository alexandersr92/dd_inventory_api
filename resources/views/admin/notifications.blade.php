@extends('admin.layout')

@section('title', 'Notificaciones')

@section('content')
@php
    $fld = 'width:100%; background: rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:8px; padding:10px 12px; font-size:14px; color: var(--text-primary); outline:none;';
    $events = [
        'notify_new_account'  => ['Nueva cuenta registrada', 'Cuando una organización se registra en la plataforma.'],
        'notify_payment'      => ['Comprobante de pago recibido', 'Cuando un cliente sube un comprobante para validar.'],
        'notify_renewal'      => ['Renovación aprobada', 'Cuando apruebas un pago y se renueva una licencia.'],
        'notify_expiring'     => ['Licencias por vencer', 'Resumen diario de licencias próximas a vencer.'],
        'notify_error_report' => ['Reporte de error de un admin', 'Cuando un usuario root reporta un problema desde el panel.'],
    ];
@endphp

<div class="header-section" style="margin-bottom:28px;">
    <div>
        <h1 style="font-size:28px; font-weight:800; letter-spacing:-0.5px;">Notificaciones</h1>
        <p style="color: var(--text-secondary); margin-top:8px;">Elige qué avisos por correo recibe el equipo root y si se notifica a los clientes.</p>
    </div>
</div>

@if(session('success'))
    <div style="background: rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:10px; padding:14px 18px; margin-bottom:24px; color: var(--success-color); font-size:14px; font-weight:500;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="background: rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:10px; padding:14px 18px; margin-bottom:24px; color: var(--danger-color); font-size:14px;">{{ $errors->first() }}</div>
@endif

<form action="{{ route('admin.notifications.update') }}" method="POST">
    @csrf

    <!-- Destinatarios -->
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:24px; margin-bottom:24px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:6px;">Destinatarios root</h2>
        <p style="color: var(--text-secondary); font-size:13px; margin-bottom:14px;">
            Correos que reciben las alertas de la plataforma, separados por coma. Si lo dejas vacío, se usan todos los usuarios root:
            <span style="color: var(--text-primary);">{{ implode(', ', $adminEmails) ?: 'ninguno' }}</span>.
        </p>
        <textarea name="notify_recipients" style="{{ $fld }} min-height:70px; resize:vertical;" placeholder="pagos@diplebill.com, soporte@diplebill.com">{{ $settings['notify_recipients'] }}</textarea>
    </div>

    <!-- Eventos root -->
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:24px; margin-bottom:24px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:18px;">Avisos al equipo root</h2>
        @foreach($events as $key => [$label, $desc])
            <label style="display:flex; align-items:flex-start; gap:14px; padding:14px 0; border-bottom:1px solid var(--border-color); cursor:pointer;">
                <span style="position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; margin-top:2px;">
                    <input type="checkbox" name="{{ $key }}" value="1" {{ $settings[$key] ? 'checked' : '' }} style="opacity:0; width:0; height:0;" onchange="this.nextElementSibling.style.background=this.checked?'var(--accent-color)':'rgba(255,255,255,0.12)'; this.nextElementSibling.firstElementChild.style.transform=this.checked?'translateX(20px)':'translateX(0)';">
                    <span style="position:absolute; inset:0; border-radius:24px; transition:.2s; background:{{ $settings[$key] ? 'var(--accent-color)' : 'rgba(255,255,255,0.12)' }};">
                        <span style="position:absolute; height:18px; width:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; transform:{{ $settings[$key] ? 'translateX(20px)' : 'translateX(0)' }};"></span>
                    </span>
                </span>
                <span>
                    <span style="display:block; font-size:14px; font-weight:600;">{{ $label }}</span>
                    <span style="display:block; font-size:12px; color: var(--text-secondary); margin-top:2px;">{{ $desc }}</span>
                </span>
            </label>
        @endforeach
    </div>

    <!-- Avisos a clientes -->
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:24px; margin-bottom:24px;">
        <h2 style="font-size:16px; font-weight:700; margin-bottom:18px;">Avisos a clientes</h2>
        <label style="display:flex; align-items:flex-start; gap:14px; cursor:pointer;">
            <span style="position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; margin-top:2px;">
                <input type="checkbox" name="notify_client_enabled" value="1" {{ $settings['notify_client_enabled'] ? 'checked' : '' }} style="opacity:0; width:0; height:0;" onchange="this.nextElementSibling.style.background=this.checked?'var(--accent-color)':'rgba(255,255,255,0.12)'; this.nextElementSibling.firstElementChild.style.transform=this.checked?'translateX(20px)':'translateX(0)';">
                <span style="position:absolute; inset:0; border-radius:24px; transition:.2s; background:{{ $settings['notify_client_enabled'] ? 'var(--accent-color)' : 'rgba(255,255,255,0.12)' }};">
                    <span style="position:absolute; height:18px; width:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s; transform:{{ $settings['notify_client_enabled'] ? 'translateX(20px)' : 'translateX(0)' }};"></span>
                </span>
            </span>
            <span>
                <span style="display:block; font-size:14px; font-weight:600;">Notificar al cliente por correo</span>
                <span style="display:block; font-size:12px; color: var(--text-secondary); margin-top:2px;">Confirmación cuando su comprobante es aprobado o rechazado, y bienvenida al registrarse.</span>
            </span>
        </label>
    </div>

    <div style="display:flex; gap:12px;">
        <button type="submit" style="background: var(--accent-gradient); color:#fff; border:none; padding:12px 24px; border-radius:10px; font-weight:600; cursor:pointer;">Guardar configuración</button>
        <button type="submit" formaction="{{ route('admin.notifications.test') }}" style="background: rgba(255,255,255,0.05); color: var(--text-primary); border:1px solid var(--border-color); padding:12px 24px; border-radius:10px; font-weight:600; cursor:pointer;">Enviar correo de prueba</button>
    </div>
</form>
@endsection
