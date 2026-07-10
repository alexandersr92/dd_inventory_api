@extends('admin.layout')

@section('title', 'Gestionar Cliente - ' . $organization->name)

@section('styles')
    .back-link {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 24px;
        transition: color 0.2s ease;
    }

    .back-link:hover {
        color: var(--text-primary);
    }

    .client-header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        margin-bottom: 32px;
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 24px 32px;
    }

    .client-title-area {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .client-title-text {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.5px;
    }

    .client-meta-badges {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Details Layout */
    .details-layout-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 32px;
        margin-bottom: 40px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .details-layout-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Section Card */
    .section-card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 32px;
    }

    .card-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 24px;
        color: var(--text-primary);
        letter-spacing: -0.2px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 12px;
    }

    /* Info list styles */
    .info-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    }

    .info-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-row-label {
        font-size: 13px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .info-row-value {
        font-size: 14px;
        color: var(--text-primary);
        font-weight: 600;
        text-align: right;
    }

    /* Stats Grid */
    .show-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    @media (max-width: 600px) {
        .show-stats-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .stat-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .stat-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
    }

    /* Modules grid */
    .modules-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }

    .module-card-toggle {
        display: block;
    }

    .module-toggle-btn {
        width: 100%;
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        padding: 16px;
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

    /* Actions buttons */
    .btn-toggle {
        background: none;
        border: none;
        cursor: pointer;
        padding: 10px 16px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
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

    /* Danger delete button */
    .btn-delete-org {
        cursor: pointer;
        padding: 10px 18px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.1px;
        transition: all 0.22s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(185, 28, 28, 0.08));
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.25);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
    }

    .btn-delete-org:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.45), inset 0 1px 0 rgba(255,255,255,0.1);
        transform: translateY(-1px);
    }

    .btn-delete-org:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    /* Modal overlay */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }

    .modal-overlay.show {
        opacity: 1;
        pointer-events: all;
    }

    .modal-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 32px 64px rgba(0,0,0,0.5);
        transform: translateY(16px) scale(0.98);
        transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-overlay.show .modal-card {
        transform: translateY(0) scale(1);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid rgba(239, 68, 68, 0.14);
    }

    .modal-title {
        font-size: 16px;
        font-weight: 700;
        color: #f87171;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 4px;
        border-radius: 6px;
        transition: color 0.2s ease;
        display: flex;
    }

    .modal-close:hover { color: var(--text-primary); }

    .modal-body { padding: 24px; }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(239, 68, 68, 0.1);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    /* Danger warning banner inside modal */
    .modal-danger-banner {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.07), rgba(185, 28, 28, 0.04));
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 24px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    .modal-danger-banner svg { flex-shrink: 0; margin-top: 2px; }

    .modal-danger-banner-title {
        font-size: 13px;
        font-weight: 700;
        color: #f87171;
        margin-bottom: 6px;
    }

    .modal-danger-banner-desc {
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.6;
    }

    .modal-danger-banner-desc strong {
        color: var(--text-primary);
    }

    /* Input field inside modal */
    .modal-input-label {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        margin-bottom: 8px;
        display: block;
    }

    .modal-input {
        width: 100%;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px 14px;
        font-family: inherit;
        font-size: 14px;
        color: var(--text-primary);
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        box-sizing: border-box;
    }

    .modal-input:focus {
        border-color: rgba(239, 68, 68, 0.5);
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .modal-error-text {
        font-size: 12px;
        color: #f87171;
        margin-top: 6px;
    }

    /* Modal action buttons */
    .btn-modal-cancel {
        background: none;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 10px 18px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-modal-cancel:hover {
        background: rgba(255,255,255,0.05);
        color: var(--text-primary);
    }

    .btn-modal-destroy {
        background: linear-gradient(135deg, #dc2626, #991b1b);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);
        transition: all 0.22s ease;
    }

    .btn-modal-destroy:hover {
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.55);
        transform: translateY(-1px);
    }

    .btn-modal-destroy:active {
        transform: translateY(0);
    }


    /* Toast Notification */
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
@endsection

@section('content')
    @php
        $invoicesCount = $organization->invoices_count;
        $totalInvoicedCanlede = $organization->invoices()->where('invoice_status', 'canceled')->sum('grand_total');
    
        $totalInvoiced = ($organization->total_invoiced - $totalInvoicedCanlede)?: 0;
        $ticketPromedio = $invoicesCount > 0 ? ($totalInvoiced / $invoicesCount) : 0;
        $totalDebt = $organization->total_debt ?: 0;
    @endphp

    <!-- Link Back -->
    <a href="{{ route('admin.dashboard', ['tab' => 'clients']) }}" class="back-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Volver a la lista de Clientes
    </a>

    {{-- Flash: éxito --}}
    @if(session('success'))
        <div style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; color: var(--success-color); font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Flash: error de eliminación --}}
    @if(session('delete_error'))
        <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; color: #f87171; font-size: 14px; font-weight: 500; display: flex; align-items: flex-start; gap: 10px;">
            <svg width="16" height="16" style="flex-shrink:0;margin-top:2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            {{ session('delete_error') }}
        </div>
    @endif

    <!-- Client Main Header -->
    <div class="client-header-container">
        <div class="client-title-area">
            <h1 class="client-title-text">{{ $organization->name }}</h1>
            <div class="client-meta-badges">
                @if($organization->status === 'active')
                    <span class="badge badge-active">Cuenta Activa</span>
                @else
                    <span class="badge badge-inactive">Cuenta Inactiva</span>
                @endif
                <span class="badge" style="background-color: rgba(255,255,255,0.03); color: var(--text-secondary); border: 1px solid var(--border-color); text-transform: capitalize;">
                    ID: {{ $organization->id }}
                </span>
            </div>
        </div>
        <div class="client-action-area" style="display: flex; gap: 10px; align-items: center;">
            <form action="{{ route('admin.clients.toggle-status', $organization->id) }}" method="POST">
                @csrf
                @if($organization->status === 'active')
                    <button type="submit" class="btn-toggle btn-toggle-deactivate">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                        Desactivar Cuenta
                    </button>
                @else
                    <button type="submit" class="btn-toggle btn-toggle-activate">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 8 8 12 12 16"></polyline><line x1="16" y1="12" x2="8" y2="12"></line></svg>
                        Activar Cuenta
                    </button>
                @endif
            </form>

            {{-- Separator --}}
            <div style="width: 1px; height: 28px; background: var(--border-color); border-radius: 2px;"></div>

            {{-- Delete Organization Button --}}
            <button
                type="button"
                class="btn-delete-org"
                onclick="openDeleteModal()"
                title="Eliminar esta organización permanentemente"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                </svg>
                Eliminar Organización
            </button>
        </div>
    </div>

    <!-- Main Grid Details -->
    <div class="details-layout-grid">
        <div class="left-column">
            <!-- Stats Indicators -->
            <div class="section-card">
                <h2 class="card-title">Métricas e Indicadores de Uso</h2>
                <div class="show-stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Facturas Emitidas</span>
                        <span class="stat-value">{{ $invoicesCount }}</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Créditos Otorgados</span>
                        <span class="stat-value">{{ $organization->credits_count }}</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Vendedores Registrados</span>
                        <span class="stat-value">{{ $organization->sellers_count }}</span>
                    </div>
                    <div class="stat-card" style="margin-top: 16px; border-color: rgba(16, 185, 129, 0.15); background-color: rgba(16, 185, 129, 0.01);">
                        <span class="stat-label" style="color: var(--success-color);">Total Facturado</span>
                        <span class="stat-value" style="color: var(--success-color);">${{ number_format($totalInvoiced, 2) }}</span>
                    </div>
                    <div class="stat-card" style="margin-top: 16px; border-color: rgba(239, 68, 68, 0.15); background-color: rgba(239, 68, 68, 0.01);">
                        <span class="stat-label" style="color: var(--danger-color);">Deuda Pendiente</span>
                        <span class="stat-value" style="color: var(--danger-color);">${{ number_format($totalDebt, 2) }}</span>
                    </div>
                    <div class="stat-card" style="margin-top: 16px; border-color: rgba(99, 102, 241, 0.15); background-color: rgba(99, 102, 241, 0.01);">
                        <span class="stat-label" style="color: #818CF8;">Ticket Promedio</span>
                        <span class="stat-value" style="color: #818CF8;">${{ number_format($ticketPromedio, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Modulos management -->
            <div class="section-card">
                <h2 class="card-title">Módulos Contratados</h2>
                <div class="modules-card-grid">
                    @foreach($allModules as $mod)
                        @php
                            $orgModule = $organization->modules->firstWhere('id', $mod->id);
                            $isActive = $orgModule && $orgModule->pivot->status === 'active';
                        @endphp
                        <div class="module-card-toggle">
                            <form action="{{ route('admin.clients.toggle-module', [$organization->id, $mod->id]) }}" method="POST" onsubmit="submitModuleAjax(event, this)">
                                @csrf
                                <button type="submit" class="module-toggle-btn {{ $isActive ? 'active' : 'inactive' }}">
                                    <span>{{ $mod->name }}</span>
                                    <div class="module-status-indicator"></div>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="right-column">
            <!-- General details card -->
            <div class="section-card">
                <h2 class="card-title">Detalles de Cuenta</h2>
                <div class="info-list">
                    <div class="info-row">
                        <span class="info-row-label">Dueño / Propietario</span>
                        <span class="info-row-value">{{ $organization->user->name ?? 'No asignado' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Email del Dueño</span>
                        <span class="info-row-value">{{ $organization->email }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Teléfono</span>
                        <span class="info-row-value">{{ $organization->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Tipo de Cuenta</span>
                        <span class="info-row-value" style="text-transform: capitalize;">{{ $organization->tenancy_type }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Base de Datos</span>
                        <span class="info-row-value">{{ $organization->db_database ?: 'Central (Compartida)' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Registro</span>
                        <span class="info-row-value">{{ $organization->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Organization Modal -->
    <div class="modal-overlay" id="delete-org-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    Eliminar Organización
                </h3>
                <button class="modal-close" onclick="closeDeleteModal()" type="button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('admin.clients.destroy', $organization->id) }}"
            >
                @csrf
                <div class="modal-body">
                    <div class="modal-danger-banner">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <div>
                            <p class="modal-danger-banner-title">Acción irreversible — no hay vuelta atrás</p>
                            <p class="modal-danger-banner-desc">
                                Estás a punto de eliminar permanentemente la organización
                                <strong>"{{ $organization->name }}"</strong>
                                y <strong>todos</strong> sus datos asociados: usuarios, vendedores, tiendas,
                                facturas, créditos y configuraciones.
                                <br><br>
                                <strong style="color: #f87171;">Esta operación no se puede deshacer.</strong>
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="modal-input-label" for="delete_admin_password">
                            Confirma tu contraseña de administrador
                        </label>
                        <input
                            type="password"
                            name="admin_password"
                            id="delete_admin_password"
                            class="modal-input"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        @error('admin_password')
                            <p class="modal-error-text">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal-destroy">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                            <path d="M10 11v6"></path>
                            <path d="M14 11v6"></path>
                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                        </svg>
                        Eliminar definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- AJAX toast feedback -->
    <div class="toast-mini" id="ajax-toast">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color: var(--success-color)"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span id="ajax-toast-text">Módulo actualizado</span>
    </div>

    <script>
        /* ---- Module toggle AJAX ---- */
        function submitModuleAjax(event, form) {
            event.preventDefault();
            const button = form.querySelector('.module-toggle-btn');
            const isActive = button.classList.contains('active');
            button.classList.toggle('active');
            button.classList.toggle('inactive');

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showMiniToast(data.message);
                } else {
                    button.classList.toggle('active', isActive);
                    button.classList.toggle('inactive', !isActive);
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                button.classList.toggle('active', isActive);
                button.classList.toggle('inactive', !isActive);
                console.error(err);
                alert('No se pudo establecer conexión con el servidor.');
            });
        }

        function showMiniToast(message) {
            const toast = document.getElementById('ajax-toast');
            const text  = document.getElementById('ajax-toast-text');
            if (toast && text) {
                text.textContent = message;
                toast.classList.add('show');
                if (window.toastTimeout) clearTimeout(window.toastTimeout);
                window.toastTimeout = setTimeout(() => toast.classList.remove('show'), 3000);
            }
        }

        /* ---- Delete org modal ---- */
        function openDeleteModal() {
            const modal = document.getElementById('delete-org-modal');
            const pwd   = document.getElementById('delete_admin_password');
            if (pwd) pwd.value = '';
            if (modal) modal.classList.add('show');
        }

        function closeDeleteModal() {
            const modal = document.getElementById('delete-org-modal');
            if (modal) modal.classList.remove('show');
        }

        // Close on backdrop click
        document.getElementById('delete-org-modal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Open modal if password validation failed (page reloaded with error)
        @if($errors->has('admin_password') || session('delete_error'))
            document.addEventListener('DOMContentLoaded', () => openDeleteModal());
        @endif
    </script>
@endsection
