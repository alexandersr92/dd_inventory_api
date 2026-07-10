@extends('admin.layout')

@section('title', 'Dashboard')

@section('styles')
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }

    .stat-card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        border-color: rgba(99, 102, 241, 0.2);
        box-shadow: 0 12px 20px -10px rgba(0, 0, 0, 0.3);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        background-color: rgba(99, 102, 241, 0.08);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-color);
    }

    .stat-info {
        display: flex;
        flex-direction: column;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 4px;
        font-weight: 500;
    }

    /* Section Card */
    .section-card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 40px;
    }

    .section-header {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        letter-spacing: -0.3px;
    }

    /* Premium Table */
    .table-container {
        width: 100%;
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .admin-table th {
        background-color: rgba(255, 255, 255, 0.02);
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
    }

    .admin-table td {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border-color);
        font-size: 14px;
        vertical-align: middle;
    }

    .admin-table tr:last-child td {
        border-bottom: none;
    }

    .admin-table tr:hover td {
        background-color: rgba(255, 255, 255, 0.01);
    }

    .client-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .client-subtext {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 3px;
    }

    /* Status Badge */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
    }

    .badge-active {
        background-color: rgba(16, 185, 129, 0.08);
        color: var(--success-color);
        border: 1px solid rgba(16, 185, 129, 0.15);
    }

    .badge-inactive {
        background-color: rgba(239, 68, 68, 0.08);
        color: var(--danger-color);
        border: 1px solid rgba(239, 68, 68, 0.15);
    }

    /* Actions buttons */
    .btn-toggle {
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-toggle-deactivate {
        color: var(--danger-color);
        background-color: rgba(239, 68, 68, 0.05);
        border: 1px solid rgba(239, 68, 68, 0.1);
    }

    .btn-toggle-deactivate:hover {
        background-color: var(--danger-color);
        color: #FFF;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .btn-toggle-activate {
        color: var(--success-color);
        background-color: rgba(16, 185, 129, 0.05);
        border: 1px solid rgba(16, 185, 129, 0.1);
    }

    .btn-toggle-activate:hover {
        background-color: var(--success-color);
        color: #FFF;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-manage-modules {
        background-color: rgba(99, 102, 241, 0.08);
        color: #818CF8;
        border: 1px solid rgba(99, 102, 241, 0.2);
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-manage-modules:hover {
        background: var(--accent-gradient);
        color: #FFF;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .btn-delete-org {
        background-color: rgba(239, 68, 68, 0.07);
        color: var(--danger-color);
        border: 1px solid rgba(239, 68, 68, 0.18);
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-delete-org:hover {
        background-color: var(--danger-color);
        color: #fff;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
    }

    .modal-danger-banner {
        background-color: rgba(239, 68, 68, 0.07);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .modal-danger-banner svg { flex-shrink: 0; margin-top: 2px; }
    .modal-danger-banner-text strong { color: var(--danger-color); display: block; margin-bottom: 4px; }
    .modal-danger-banner-text span { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }



    /* Modal Overlay Backdrop */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(5, 7, 12, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-overlay.show {
        opacity: 1;
        pointer-events: auto;
    }

    .modal-card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 32px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        transform: translateY(-20px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-overlay.show .modal-card {
        transform: translateY(0);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border-color);
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 6px;
        border-radius: 6px;
    }

    .modal-close:hover {
        color: var(--text-primary);
        background-color: rgba(255, 255, 255, 0.04);
    }

    .modal-body {
        margin-bottom: 24px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }

    /* Modal Form Grid */
    .form-grid {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .form-group-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-label-text {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .input-field {
        background-color: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        padding: 12px 14px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
        outline: none;
        transition: all 0.2s ease;
    }

    .input-field:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    .form-section-separator {
        font-size: 13px;
        font-weight: 600;
        color: var(--accent-color);
        margin-top: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Modules Grid layout inside modal */
    .modal-modules-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        max-height: 320px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .modal-modules-grid::-webkit-scrollbar {
        width: 6px;
    }

    .modal-modules-grid::-webkit-scrollbar-thumb {
        background-color: var(--border-color);
        border-radius: 3px;
    }

    .module-card-toggle {
        display: block;
    }

    .module-toggle-btn {
        width: 100%;
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        padding: 14px 16px;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
    }

    .module-toggle-btn.active {
        background-color: rgba(99, 102, 241, 0.08);
        color: #818CF8;
        border-color: rgba(99, 102, 241, 0.4);
    }

    .module-toggle-btn.active:hover {
        background-color: rgba(99, 102, 241, 0.15);
        border-color: rgba(99, 102, 241, 0.6);
    }

    .module-toggle-btn.inactive {
        color: var(--text-secondary);
    }

    .module-toggle-btn.inactive:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .module-status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #6B7280;
    }

    .module-toggle-btn.active .module-status-indicator {
        background-color: var(--success-color);
        box-shadow: 0 0 8px var(--success-color);
    }

    /* Toast Notification inside modal */
    .toast-mini {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(10px);
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        padding: 12px 20px;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 500;
        z-index: 200;
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .toast-mini.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    /* Buttons layout */
    .btn-add-new {
        background: var(--accent-gradient);
        border: none;
        color: #FFF;
        padding: 10px 18px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-add-new:hover {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        transform: translateY(-1px);
    }

    .alert-error-container {
        background-color: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.15);
        color: var(--danger-color);
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 14px;
        font-weight: 500;
    }

    /* Modal Client Options Specific Styles */
    .modal-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .info-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .info-val {
        font-size: 14px;
        color: var(--text-primary);
        font-weight: 500;
        word-break: break-all;
    }

    .status-action-section {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .status-current {
        display: flex;
        flex-direction: column;
    }

    .status-btn-container {
        flex-shrink: 0;
    }

    .btn-action-primary {
        background: var(--accent-gradient);
        border: none;
        color: #FFF;
        padding: 10px 18px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    
    .btn-action-primary:hover {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        transform: translateY(-1px);
    }
    
    .btn-action-secondary {
        background-color: transparent;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 10px 18px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    
    .btn-action-secondary:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
        border-color: rgba(255, 255, 255, 0.2);
    }

    /* Modules Usage Styling */
    .modules-usage-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 10px;
    }

    .usage-card {
        background-color: rgba(255, 255, 255, 0.01);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        transition: all 0.2s ease;
    }

    .usage-card:hover {
        border-color: rgba(99, 102, 241, 0.2);
        background-color: rgba(255, 255, 255, 0.02);
        transform: translateY(-1px);
    }

    .usage-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .usage-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .usage-count {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .usage-bar-container {
        width: 100%;
        height: 6px;
        background-color: rgba(255, 255, 255, 0.05);
        border-radius: 9999px;
        overflow: hidden;
    }

    .usage-bar {
        height: 100%;
        background: var(--accent-gradient);
        border-radius: 9999px;
        transition: width 0.6s ease;
    }

    .btn-download {
        color: #3B82F6;
        background-color: rgba(59, 130, 246, 0.05);
        border: 1px solid rgba(59, 130, 246, 0.1);
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    
    .btn-download:hover {
        background-color: #3B82F6;
        color: #FFF;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        transform: translateY(-1px);
    }

    .btn-delete {
        color: var(--danger-color);
        background-color: rgba(239, 68, 68, 0.05);
        border: 1px solid rgba(239, 68, 68, 0.1);
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .btn-delete:hover {
        background-color: var(--danger-color);
        color: #FFF;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        transform: translateY(-1px);
    }
@endsection

@section('content')
    @php
        $currentTab = request('tab', 'dashboard');
    @endphp

    <!-- Error Alerts (Visible on any tab) -->
    @if ($errors->any())
        <div class="alert-error-container">
            <strong>Ocurrió un error al procesar el formulario:</strong>
            <ul style="margin-top: 8px; margin-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($currentTab === 'dashboard')
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="header-title">Panel de Administración</h1>
                <p class="header-subtitle">Monitoreo general de clientes, módulos y estadísticas de la plataforma.</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ $totalClients }}</span>
                    <span class="stat-label">Clientes Totales</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success-color); background-color: rgba(16, 185, 129, 0.08)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ $activeClients }}</span>
                    <span class="stat-label">Clientes Activos</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: #F59E0B; background-color: rgba(245, 158, 11, 0.08)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ $totalStores }}</span>
                    <span class="stat-label">Sucursales creadas</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: #3B82F6; background-color: rgba(59, 130, 246, 0.08)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ $totalUsers }}</span>
                    <span class="stat-label">Usuarios en Plataforma</span>
                </div>
            </div>
        </div>

        <!-- Modules Usage Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Uso de Módulos del Sistema</h2>
            </div>
            
            <div class="modules-usage-grid">
                @foreach($modulesUsage as $modUsage)
                    @php
                        $percentage = $totalClients > 0 ? round(($modUsage->organization_count / $totalClients) * 100) : 0;
                    @endphp
                    <div class="usage-card">
                        <div class="usage-info">
                            <span class="usage-name">{{ $modUsage->name }}</span>
                            <span class="usage-count">{{ $modUsage->organization_count }} / {{ $totalClients }} clientes ({{ $percentage }}%)</span>
                        </div>
                        <div class="usage-bar-container">
                            <div class="usage-bar" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($currentTab === 'clients')
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="header-title">Gestión de Clientes</h1>
                <p class="header-subtitle">Administra los accesos de clientes, base de datos y asignación de módulos contratados.</p>
            </div>
        </div>

        <!-- Clients Table Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Organizaciones Clientes</h2>
                <button class="btn-add-new" onclick="openModal('create-client-modal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Crear Cliente
                </button>
            </div>
            
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Cliente / Organización</th>
                            <th>Contacto</th>
                            <th>Métricas de Uso</th>
                            <th>Estado</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($organizations as $org)
                            @php
                                $invoicesCount = $org->invoices_count;
                                $totalInvoiced = $org->total_invoiced ?: 0;
                                $ticketPromedio = $invoicesCount > 0 ? ($totalInvoiced / $invoicesCount) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="client-name">{{ $org->name }}</div>
                                    <div class="client-subtext">ID: {{ $org->id }}</div>
                                    <div class="client-subtext" style="margin-top: 4px;">
                                        <span class="badge" style="padding: 2px 6px; font-size: 10px; background-color: rgba(255,255,255,0.02); border: 1px solid var(--border-color); color: var(--text-secondary); text-transform: capitalize;">
                                            {{ $org->tenancy_type }} ({{ $org->db_database ?: 'Compartida' }})
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="client-name">{{ $org->user->name ?? 'Propietario no asignado' }}</div>
                                    <div class="client-subtext">{{ $org->email }}</div>
                                </td>
                                <td>
                                    <div style="font-size: 13px; font-weight: 500;">
                                        <span style="color: var(--text-secondary)">Facturas:</span> <strong style="color: var(--text-primary)">{{ $org->invoices_count }}</strong>
                                    </div>
                                    <div style="font-size: 12px; margin-top: 3px; color: var(--text-secondary);">
                                        Ticket Prom: <strong style="color: #818CF8">${{ number_format($ticketPromedio, 2) }}</strong>
                                    </div>
                                    <div style="font-size: 12px; margin-top: 3px; color: var(--text-secondary);">
                                        Vendedores: <strong style="color: var(--text-primary)">{{ $org->sellers_count }}</strong>
                                    </div>
                                </td>
                                <td>
                                    @if($org->status === 'active')
                                        <span class="badge badge-active">Activo</span>
                                    @else
                                        <span class="badge badge-inactive">Inactivo</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                        <a href="{{ route('admin.clients.show', $org->id) }}" class="btn-manage-modules" style="text-decoration: none;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2 2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                            Gestionar
                                        </a>
                                       
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 48px;">
                                    No hay organizaciones clientes registradas en la plataforma.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($currentTab === 'admins')
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="header-title">Usuarios Root</h1>
                <p class="header-subtitle">Controla el personal con acceso administrativo root a la plataforma central.</p>
            </div>
        </div>

        <!-- Administrators Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Administradores de la Plataforma (Root Users)</h2>
                <button class="btn-add-new" onclick="openModal('create-admin-modal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Crear Admin
                </button>
            </div>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo electrónico</th>
                            <th>Estado</th>
                            <th>Fecha de Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($admins as $adminUser)
                            <tr>
                                <td class="client-name">{{ $adminUser->name }}</td>
                                <td>{{ $adminUser->email }}</td>
                                <td>
                                    @if($adminUser->status === 'active')
                                        <span class="badge badge-active">Activo</span>
                                    @else
                                        <span class="badge badge-inactive">Inactivo</span>
                                    @endif
                                </td>
                                <td class="client-subtext">{{ $adminUser->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($currentTab === 'backups')
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="header-title">Copias de Seguridad</h1>
                <p class="header-subtitle">Gestiona y descarga los respaldos de la base de datos mysql central.</p>
            </div>
        </div>

        <!-- Backups Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Respaldos Generados</h2>
                <form action="{{ route('admin.backups.generate') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn-add-new">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Generar Backup Ahora
                    </button>
                </form>
            </div>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Nombre de Archivo</th>
                            <th>Tamaño</th>
                            <th>Fecha de Creación</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backups as $backup)
                            <tr>
                                <td class="client-name">{{ $backup['name'] }}</td>
                                <td>{{ $backup['size'] }}</td>
                                <td>{{ $backup['created_at'] }}</td>
                                <td style="text-align: right; display: flex; justify-content: flex-end; gap: 8px; align-items: center;">
                                    <a href="{{ route('admin.backups.download', $backup['name']) }}" class="btn-download">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                        Descargar
                                    </a>
                                    <form action="{{ route('admin.backups.delete', $backup['name']) }}" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta copia de seguridad? Esta acción no se puede deshacer.')" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn-delete">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 32px;">
                                    No se encontraron copias de seguridad de la base de datos.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!--           MODALS CONTAINER AREA            -->
    <!-- ========================================== -->

    <!-- Modal: Create Client -->
    <div class="modal-overlay" id="create-client-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Nueva Organización</h3>
                <button class="modal-close" onclick="closeModal('create-client-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form action="{{ route('admin.clients.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-section-separator">Datos de la Organización</div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="name">Nombre de la Empresa</label>
                            <input type="text" name="name" id="name" class="input-field" placeholder="Empresa S.A." required>
                        </div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="email">Email Organizacional</label>
                            <input type="email" name="email" id="email" class="input-field" placeholder="contacto@empresa.com" required>
                        </div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="phone">Teléfono</label>
                            <input type="text" name="phone" id="phone" class="input-field" placeholder="+506 8888 8888" required>
                        </div>

                        <div class="form-section-separator">Propietario de la Cuenta</div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="owner_name">Nombre Completo</label>
                            <input type="text" name="owner_name" id="owner_name" class="input-field" placeholder="Juan Pérez" required>
                        </div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="owner_email">Email de Acceso</label>
                            <input type="email" name="owner_email" id="owner_email" class="input-field" placeholder="juan.perez@empresa.com" required>
                        </div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="owner_password">Contraseña (Mín. 8 caracteres)</label>
                            <input type="password" name="owner_password" id="owner_password" class="input-field" placeholder="••••••••" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action-secondary" onclick="closeModal('create-client-modal')">Cancelar</button>
                    <button type="submit" class="btn-action-primary">Crear Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Delete Organization (with password confirmation) -->
    <div class="modal-overlay" id="delete-org-modal">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header" style="border-bottom-color: rgba(239, 68, 68, 0.15);">
                <h3 class="modal-title" style="color: var(--danger-color);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Eliminar Organización
                </h3>
                <button class="modal-close" onclick="closeModal('delete-org-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form id="delete-org-form" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="modal-danger-banner">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--danger-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <div class="modal-danger-banner-text">
                            <strong>Acción irreversible</strong>
                            <span>Estás a punto de eliminar permanentemente la organización <strong id="delete-org-name-label" style="color:var(--text-primary);"></strong> junto con todos sus usuarios, vendedores, tiendas, facturas, créditos y demás datos. <strong style="color:var(--danger-color);">Esta operación no se puede deshacer.</strong></span>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label class="form-label-text" for="delete_admin_password">
                            Confirma tu contraseña de administrador para continuar
                        </label>
                        <input
                            type="password"
                            name="admin_password"
                            id="delete_admin_password"
                            class="input-field"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        @error('admin_password')
                            <p style="color: var(--danger-color); font-size: 12px; margin-top: 6px;">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: rgba(239, 68, 68, 0.1);">
                    <button type="button" class="btn-action-secondary" onclick="closeModal('delete-org-modal')">Cancelar</button>
                    <button
                        type="submit"
                        style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;"
                        onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'"
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                        Eliminar definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Create Admin -->

    <div class="modal-overlay" id="create-admin-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Crear Nuevo Administrador</h3>
                <button class="modal-close" onclick="closeModal('create-admin-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form action="{{ route('admin.admins.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group-item">
                            <label class="form-label-text" for="admin_name">Nombre Completo</label>
                            <input type="text" name="name" id="admin_name" class="input-field" placeholder="Super Administrador" required>
                        </div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="admin_email">Email de Acceso</label>
                            <input type="email" name="email" id="admin_email" class="input-field" placeholder="admin@myplatform.com" required>
                        </div>
                        <div class="form-group-item">
                            <label class="form-label-text" for="admin_password">Contraseña (Mín. 8 caracteres)</label>
                            <input type="password" name="password" id="admin_password" class="input-field" placeholder="••••••••" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action-secondary" onclick="closeModal('create-admin-modal')">Cancelar</button>
                    <button type="submit" class="btn-action-primary">Crear Administrador</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mini-toast for AJAX updates -->
    <div class="toast-mini" id="ajax-toast">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color: var(--success-color)"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span id="ajax-toast-text">Módulo actualizado</span>
    </div>

    <!-- Modals JavaScript logic -->
    <script>
        // Open Modal
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('show');
            }
        }

        // Close Modal
        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('show');
            }
        }

        // Intercept Modal Clicks on background to close
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // AJAX submit for module toggles
        function submitModuleAjax(event, form) {
            event.preventDefault();
            const button = form.querySelector('.module-toggle-btn');
            
            // Save state to rollback if needed
            const isActive = button.classList.contains('active');
            
            // Optimistic UI update
            button.classList.toggle('active');
            button.classList.toggle('inactive');

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMiniToast(data.message);
                } else {
                    // Rollback
                    button.classList.toggle('active', isActive);
                    button.classList.toggle('inactive', !isActive);
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                // Rollback
                button.classList.toggle('active', isActive);
                button.classList.toggle('inactive', !isActive);
                console.error('Error:', error);
                alert('No se pudo establecer conexión con el servidor.');
            });
        }

        // Show a mini-toast confirmation at the bottom of the screen
        function showMiniToast(message) {
            const toast = document.getElementById('ajax-toast');
            const text = document.getElementById('ajax-toast-text');
            if (toast && text) {
                text.textContent = message;
                toast.classList.add('show');
                
                // Clear any previous timeout
                if (window.toastTimeout) {
                    clearTimeout(window.toastTimeout);
                }
                
                window.toastTimeout = setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
        // Open delete-org modal with correct org ID and name
        function openDeleteModal(orgId, orgName) {
            const form = document.getElementById('delete-org-form');
            const nameLabel = document.getElementById('delete-org-name-label');
            const passwordInput = document.getElementById('delete_admin_password');

            // Build the action URL dynamically using the org ID
            const baseUrl = '{{ url("admin/clients") }}';
            form.action = baseUrl + '/' + orgId + '/destroy';

            // Populate the org name in the warning banner
            if (nameLabel) nameLabel.textContent = '"' + orgName + '"';

            // Clear previous password entry every time the modal opens
            if (passwordInput) passwordInput.value = '';

            openModal('delete-org-modal');
        }
    </script>
@endsection
