@extends('admin.layout')

@section('title', 'Pagos')

@section('content')
@php
    $fld = 'width:100%; background: rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:8px; padding:10px 12px; font-size:14px; color: var(--text-primary); outline:none;';
    $lbl = 'display:block; font-size:13px; font-weight:500; margin-bottom:6px;';
@endphp

<div class="header-section" style="margin-bottom: 28px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.5px;">Pagos</h1>
        <p style="color: var(--text-secondary); margin-top: 8px;">Métodos de pago y validación de comprobantes de renovación.</p>
    </div>
    <button onclick="openProviderForm()" style="background: var(--accent-gradient); color:#fff; border:none; padding:12px 20px; border-radius:10px; font-weight:600; cursor:pointer;">+ Método de pago</button>
</div>

@if(session('success'))
    <div style="background: rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:10px; padding:14px 18px; margin-bottom:24px; color: var(--success-color); font-size:14px; font-weight:500;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="background: rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:10px; padding:14px 18px; margin-bottom:24px; color: var(--danger-color); font-size:14px;">{{ $errors->first() }}</div>
@endif

<!-- Comprobantes pendientes -->
<div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:24px; margin-bottom:28px;">
    <h2 style="font-size:16px; font-weight:700; margin-bottom:18px;">Comprobantes por validar
        @if($pending->isNotEmpty())<span style="font-size:12px; background:#F59E0B; color:#000; padding:2px 8px; border-radius:10px; margin-left:8px;">{{ $pending->count() }}</span>@endif
    </h2>

    @forelse($pending as $s)
        <div style="border:1px solid var(--border-color); border-radius:12px; padding:16px; margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px; align-items:flex-start;">
                <div>
                    <strong style="font-size:15px;">{{ $s->organization?->name ?? '—' }}</strong>
                    <div style="font-size:13px; color: var(--text-secondary); margin-top:4px;">
                        Plan: {{ $s->plan?->name ?? 'No especificado' }} ·
                        Monto: {{ number_format($s->amount, 2) }} {{ $s->currency }}
                        @if($s->reference) · Ref: {{ $s->reference }} @endif
                    </div>
                    <div style="font-size:12px; color: var(--text-secondary); margin-top:2px;">Enviado: {{ $s->created_at->format('d/m/Y H:i') }}</div>
                </div>
                <a href="{{ route('admin.payments.receipt', $s->id) }}" target="_blank" style="background: rgba(99,102,241,0.1); color:#818CF8; border:1px solid rgba(99,102,241,0.4); padding:8px 14px; border-radius:8px; font-weight:600; font-size:13px; text-decoration:none;">Ver comprobante</a>
            </div>
            <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
                <form action="{{ route('admin.payments.approve', $s->id) }}" method="POST" onsubmit="return confirm('¿Aprobar y renovar la licencia de {{ $s->organization?->name }}?')">
                    @csrf
                    <button type="submit" style="background: var(--success-gradient); color:#fff; border:none; padding:9px 18px; border-radius:8px; font-weight:600; cursor:pointer;">Aprobar y renovar</button>
                </form>
                <button onclick="openReject('{{ $s->id }}')" style="background: rgba(239,68,68,0.1); color: var(--danger-color); border:1px solid rgba(239,68,68,0.3); padding:9px 18px; border-radius:8px; font-weight:600; cursor:pointer;">Rechazar</button>
            </div>
        </div>
    @empty
        <p style="color: var(--text-secondary); font-size:14px;">No hay comprobantes pendientes de validación.</p>
    @endforelse
</div>

<!-- Métodos de pago -->
<div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:24px; margin-bottom:28px;">
    <h2 style="font-size:16px; font-weight:700; margin-bottom:18px;">Métodos de pago</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px;">
        @foreach($providers as $p)
            <div style="border:1px solid var(--border-color); border-radius:12px; padding:18px; {{ $p->is_active ? '' : 'opacity:0.55;' }}">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <strong style="font-size:15px;">{{ $p->name }}</strong>
                        <div style="font-size:12px; color: var(--text-secondary); font-family:monospace; margin-top:2px;">{{ $p->driver }} · {{ $p->mode }}</div>
                    </div>
                    <div style="display:flex; gap:4px;">
                        @if($p->is_default)<span style="font-size:10px; background:rgba(99,102,241,0.15); color:#818CF8; padding:2px 6px; border-radius:5px;">Por defecto</span>@endif
                        <span style="font-size:10px; padding:2px 6px; border-radius:5px; {{ $p->is_active ? 'background:rgba(16,185,129,0.12); color:#34D399;' : 'background:rgba(156,163,175,0.12); color:var(--text-secondary);' }}">{{ $p->is_active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                </div>
                @if($p->instructions)<p style="font-size:12px; color: var(--text-secondary); margin-top:10px; white-space:pre-line;">{{ Str::limit($p->instructions, 120) }}</p>@endif
                <div style="display:flex; gap:8px; margin-top:14px;">
                    <button onclick='editProvider(@json($p))' style="flex:1; background:rgba(99,102,241,0.1); color:#818CF8; border:1px solid rgba(99,102,241,0.4); padding:7px; border-radius:7px; font-weight:600; cursor:pointer; font-size:13px;">Editar</button>
                    <form action="{{ route('admin.payments.providers.toggle', $p->id) }}" method="POST" style="flex:1;">@csrf
                        <button type="submit" style="width:100%; background:rgba(255,255,255,0.05); color:var(--text-secondary); border:1px solid var(--border-color); padding:7px; border-radius:7px; font-weight:600; cursor:pointer; font-size:13px;">{{ $p->is_active ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                    <form action="{{ route('admin.payments.providers.delete', $p->id) }}" method="POST" onsubmit="return confirm('¿Eliminar este método?')">@csrf
                        <button type="submit" style="background:rgba(239,68,68,0.1); color:var(--danger-color); border:1px solid rgba(239,68,68,0.3); padding:7px 10px; border-radius:7px; font-weight:600; cursor:pointer; font-size:13px;">✕</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Historial -->
@if($recent->isNotEmpty())
<div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:24px;">
    <h2 style="font-size:16px; font-weight:700; margin-bottom:18px;">Comprobantes revisados (recientes)</h2>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead><tr style="text-align:left; color:var(--text-secondary); font-size:11px; text-transform:uppercase;">
            <th style="padding:10px 12px; border-bottom:1px solid var(--border-color);">Organización</th>
            <th style="padding:10px 12px; border-bottom:1px solid var(--border-color);">Plan</th>
            <th style="padding:10px 12px; border-bottom:1px solid var(--border-color);">Monto</th>
            <th style="padding:10px 12px; border-bottom:1px solid var(--border-color);">Estado</th>
            <th style="padding:10px 12px; border-bottom:1px solid var(--border-color);">Revisado</th>
            <th style="padding:10px 12px; border-bottom:1px solid var(--border-color);">Factura</th>
        </tr></thead>
        <tbody>
            @foreach($recent as $s)
            <tr>
                <td style="padding:10px 12px; border-bottom:1px solid var(--border-color);">{{ $s->organization?->name ?? '—' }}</td>
                <td style="padding:10px 12px; border-bottom:1px solid var(--border-color); color:var(--text-secondary);">{{ $s->plan?->name ?? '—' }}</td>
                <td style="padding:10px 12px; border-bottom:1px solid var(--border-color);">{{ number_format($s->amount, 2) }} {{ $s->currency }}</td>
                <td style="padding:10px 12px; border-bottom:1px solid var(--border-color);">
                    <span style="font-size:11px; padding:2px 8px; border-radius:6px; {{ $s->status === 'approved' ? 'background:rgba(16,185,129,0.12); color:#34D399;' : 'background:rgba(239,68,68,0.12); color:#f87171;' }}">{{ $s->status === 'approved' ? 'Aprobado' : 'Rechazado' }}</span>
                </td>
                <td style="padding:10px 12px; border-bottom:1px solid var(--border-color); color:var(--text-secondary);">{{ $s->reviewed_at?->format('d/m/Y H:i') }}</td>
                <td style="padding:10px 12px; border-bottom:1px solid var(--border-color);">
                    @if($s->status === 'approved')
                        <a href="{{ route('admin.payments.invoice', $s->id) }}" target="_blank" style="color:#818CF8; text-decoration:none; font-weight:600;">Ver factura ↗</a>
                    @else
                        <span style="color:var(--text-secondary);">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- Modal método de pago -->
<div id="providerModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
    <div style="background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:28px; width:100%; max-width:460px; max-height:90vh; overflow:auto;">
        <h2 id="providerModalTitle" style="font-size:18px; font-weight:700; margin-bottom:20px;">Nuevo método de pago</h2>
        <form id="providerForm" method="POST">
            @csrf
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Nombre</label><input name="name" id="pr_name" required style="{{ $fld }}" placeholder="Ej. Transferencia BAC"></div>
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Tipo de método</label>
                <input type="hidden" name="driver" id="pr_driver" value="transfer">
                <div style="{{ $fld }} opacity:0.7;">Transferencia / depósito (validación con comprobante)</div>
            </div>
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Instrucciones para el cliente</label><textarea name="instructions" id="pr_instructions" style="{{ $fld }} min-height:90px; resize:vertical;" placeholder="Datos de la cuenta bancaria, pasos para pagar…"></textarea></div>
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Modo</label>
                <select name="mode" id="pr_mode" style="{{ $fld }}"><option value="live">Producción</option><option value="test">Pruebas</option></select>
            </div>
            <div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" id="pr_active" value="1" checked> Activo</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" id="pr_default" value="1"> Por defecto</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px;"><input type="hidden" name="supports_receipt" value="0"><input type="checkbox" name="supports_receipt" id="pr_receipt" value="1" checked> Requiere comprobante</label>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeProviderForm()" style="flex:1; background:rgba(255,255,255,0.05); color:var(--text-secondary); border:1px solid var(--border-color); padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                <button type="submit" style="flex:1; background:var(--accent-gradient); color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal rechazo -->
<div id="rejectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
    <div style="background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:28px; width:100%; max-width:420px;">
        <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Rechazar comprobante</h2>
        <form id="rejectForm" method="POST">
            @csrf
            <label style="{{ $lbl }}">Motivo del rechazo (visible para el cliente)</label>
            <textarea name="admin_notes" required style="{{ $fld }} min-height:90px; resize:vertical;" placeholder="Ej. El comprobante no coincide con el monto del plan."></textarea>
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button type="button" onclick="closeReject()" style="flex:1; background:rgba(255,255,255,0.05); color:var(--text-secondary); border:1px solid var(--border-color); padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                <button type="submit" style="flex:1; background:var(--danger-gradient); color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Rechazar</button>
            </div>
        </form>
    </div>
</div>

<script>
    const PROVIDER_STORE = "{{ route('admin.payments.providers.store') }}";
    function openProviderForm() {
        document.getElementById('providerModalTitle').textContent = 'Nuevo método de pago';
        document.getElementById('providerForm').reset();
        document.getElementById('providerForm').action = PROVIDER_STORE;
        document.getElementById('pr_active').checked = true;
        document.getElementById('pr_receipt').checked = true;
        document.getElementById('providerModal').style.display = 'flex';
    }
    function editProvider(p) {
        document.getElementById('providerModalTitle').textContent = 'Editar método de pago';
        document.getElementById('providerForm').action = '/admin/payments/providers/' + p.id + '/update';
        document.getElementById('pr_name').value = p.name;
        document.getElementById('pr_driver').value = p.driver;
        document.getElementById('pr_instructions').value = p.instructions || '';
        document.getElementById('pr_mode').value = p.mode;
        document.getElementById('pr_active').checked = !!p.is_active;
        document.getElementById('pr_default').checked = !!p.is_default;
        document.getElementById('pr_receipt').checked = !!p.supports_receipt;
        document.getElementById('providerModal').style.display = 'flex';
    }
    function closeProviderForm() { document.getElementById('providerModal').style.display = 'none'; }

    function openReject(id) {
        document.getElementById('rejectForm').action = '/admin/payments/' + id + '/reject';
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeReject() { document.getElementById('rejectModal').style.display = 'none'; }
</script>
@endsection
