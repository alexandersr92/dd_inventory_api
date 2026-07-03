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
        <div class="client-action-area">
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

    <!-- AJAX toast feedback -->
    <div class="toast-mini" id="ajax-toast">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color: var(--success-color)"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span id="ajax-toast-text">Módulo actualizado</span>
    </div>

    <!-- Script AJAX modules toggles -->
    <script>
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

        function showMiniToast(message) {
            const toast = document.getElementById('ajax-toast');
            const text = document.getElementById('ajax-toast-text');
            if (toast && text) {
                text.textContent = message;
                toast.classList.add('show');
                
                if (window.toastTimeout) {
                    clearTimeout(window.toastTimeout);
                }
                
                window.toastTimeout = setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
    </script>
@endsection
