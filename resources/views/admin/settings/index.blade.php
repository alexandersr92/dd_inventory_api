@extends('admin.layout')

@section('title', 'Configuración Global')

@section('content')
<div class="header-section" style="margin-bottom: 32px;">
    <h1 style="font-size: 28px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px;">Configuración Global</h1>
    <p style="color: var(--text-secondary); margin-top: 8px;">Administra variables que aplican a todo el sistema.</p>
</div>

@if(session('success'))
    <div style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; color: var(--success-color); font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        {{ session('success') }}
    </div>
@endif

<div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px;">
    <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 24px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">Mensaje de Licencia Expirada</h2>
    
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
        Este texto será el mensaje genérico que verán TODOS los clientes en las aplicaciones (POS, Móvil y Web) cuando su licencia haya vencido. Úsalo para dejar un número de WhatsApp de soporte o instrucciones de pago generales.
    </p>

    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        <div style="margin-bottom: 24px;">
            <label style="display: block; font-size: 14px; font-weight: 500; color: var(--text-primary); margin-bottom: 8px;">Mensaje de expiración</label>
            <textarea 
                name="license_support_message" 
                style="width: 100%; min-height: 100px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; font-family: inherit; font-size: 14px; color: var(--text-primary); resize: vertical; outline: none;"
                placeholder="Ej. Tu licencia ha expirado. Para renovar, comunícate al WhatsApp +123456789."
            >{{ old('license_support_message', $supportMessage) }}</textarea>
        </div>

        <h2 style="font-size: 16px; font-weight: 600; margin-top: 36px; margin-bottom: 24px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">Configuración de Google OAuth</h2>
        
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
            Define las credenciales globales del cliente OAuth de Google de tu consola de desarrollador para la autenticación social en el sistema.
        </p>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 8px;">Google Client ID</label>
            <input 
                type="text" 
                name="google_client_id" 
                value="{{ old('google_client_id', $googleClientId) }}"
                style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; font-size: 14px; color: var(--text-primary); outline: none;"
                placeholder="Ingresa el Client ID obtenido de Google Cloud"
            />
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 8px;">Google Client Secret</label>
            <input 
                type="password" 
                name="google_client_secret" 
                value="{{ old('google_client_secret', $googleClientSecret) }}"
                style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; font-size: 14px; color: var(--text-primary); outline: none;"
                placeholder="Ingresa el Client Secret obtenido de Google Cloud"
            />
        </div>

        <div style="margin-bottom: 28px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 8px;">Google Redirect URI</label>
            <input
                type="text"
                name="google_redirect_uri"
                value="{{ old('google_redirect_uri', $googleRedirectUri) }}"
                style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; font-size: 14px; color: var(--text-primary); outline: none;"
                placeholder="https://tudominio.com/api/v1/auth/google/callback"
            />
        </div>

        <h2 style="font-size: 16px; font-weight: 600; margin-top: 36px; margin-bottom: 24px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">Datos del emisor (Facturas de suscripción)</h2>

        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
            Estos datos aparecen como emisor en la factura PDF que se genera y se envía al cliente cuando apruebas su comprobante de pago.
        </p>

        @php
            $fld = 'width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; font-size: 14px; color: var(--text-primary); outline: none;';
            $lbl = 'display: block; font-size: 13px; font-weight: 500; color: var(--text-primary); margin-bottom: 8px;';
        @endphp

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div>
                <label style="{{ $lbl }}">Razón social / Nombre</label>
                <input type="text" name="company_legal_name" value="{{ old('company_legal_name', $company['company_legal_name']) }}" style="{{ $fld }}" placeholder="Ej. DipleBill S.A.">
            </div>
            <div>
                <label style="{{ $lbl }}">RUC / N° fiscal</label>
                <input type="text" name="company_ruc" value="{{ old('company_ruc', $company['company_ruc']) }}" style="{{ $fld }}" placeholder="Ej. J0310000000000">
            </div>
        </div>

        <div style="margin-bottom:20px;">
            <label style="{{ $lbl }}">Dirección</label>
            <input type="text" name="company_address" value="{{ old('company_address', $company['company_address']) }}" style="{{ $fld }}" placeholder="Ej. Managua, Nicaragua">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:28px;">
            <div>
                <label style="{{ $lbl }}">Teléfono</label>
                <input type="text" name="company_phone" value="{{ old('company_phone', $company['company_phone']) }}" style="{{ $fld }}" placeholder="+505 ...">
            </div>
            <div>
                <label style="{{ $lbl }}">Correo</label>
                <input type="email" name="company_email" value="{{ old('company_email', $company['company_email']) }}" style="{{ $fld }}" placeholder="facturacion@diplebill.com">
            </div>
            <div>
                <label style="{{ $lbl }}">Sitio web</label>
                <input type="text" name="company_website" value="{{ old('company_website', $company['company_website']) }}" style="{{ $fld }}" placeholder="www.diplebill.com">
            </div>
        </div>

        <button type="submit" style="background: rgba(99, 102, 241, 0.1); color: #818CF8; border: 1px solid rgba(99, 102, 241, 0.4); padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
            Guardar Configuración
        </button>
    </form>
</div>
@endsection
