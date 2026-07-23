@extends('admin.layout')

@section('title', 'Planes')

@section('content')
<div class="header-section" style="margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.5px;">Planes</h1>
        <p style="color: var(--text-secondary); margin-top: 8px;">Planes funcionales con límites que se asignan a las organizaciones.</p>
    </div>
    <button onclick="openPlanForm()" style="background: var(--accent-gradient); color: #fff; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer;">+ Nuevo plan</button>
</div>

@if(session('success'))
    <div style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; color: var(--success-color); font-size: 14px; font-weight: 500;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; color: var(--danger-color); font-size: 14px;">{{ $errors->first() }}</div>
@endif

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    @forelse($plans as $plan)
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 12px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 700;">{{ $plan->name }}</h3>
                    <p style="color: var(--text-secondary); font-size: 13px; margin-top: 2px;">{{ $plan->slug }}</p>
                </div>
                <span style="font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px; {{ $plan->is_active ? 'background: rgba(16,185,129,0.12); color:#34D399;' : 'background: rgba(156,163,175,0.12); color: var(--text-secondary);' }}">{{ $plan->is_active ? 'Activo' : 'Inactivo' }}</span>
            </div>
            <p style="font-size: 24px; font-weight: 800; margin: 8px 0 2px;">{{ number_format($plan->price_monthly, 2) }} <span style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">{{ $plan->currency }} / mes</span></p>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 0 0 6px;">Anual: <strong style="color: var(--text-primary);">{{ number_format($plan->price_annual, 2) }} {{ $plan->currency }}</strong>@if($plan->is_featured) &nbsp;·&nbsp; <span style="color:#818CF8; font-weight:600;">Destacado</span>@endif</p>
            <ul style="list-style:none; padding:0; margin: 16px 0; font-size: 13px; color: var(--text-secondary); display:flex; flex-direction:column; gap:6px;">
                <li>Vendedores: <strong style="color: var(--text-primary);">{{ is_null($plan->max_sellers) ? 'Ilimitado' : $plan->max_sellers }}</strong></li>
                <li>Sucursales: <strong style="color: var(--text-primary);">{{ is_null($plan->max_stores) ? 'Ilimitado' : $plan->max_stores }}</strong></li>
                <li>Facturas/mes: <strong style="color: var(--text-primary);">{{ is_null($plan->max_monthly_invoices) ? 'Ilimitado' : $plan->max_monthly_invoices }}</strong></li>
                <li>Tenencia: <strong style="color: var(--text-primary);">{{ $plan->tenancy_type === 'dedicated' ? 'BD dedicada' : 'Compartida' }}</strong></li>
            </ul>
            <div style="display:flex; gap:8px;">
                <button onclick='editPlan(@json($plan))' style="flex:1; background: rgba(99,102,241,0.1); color:#818CF8; border:1px solid rgba(99,102,241,0.4); padding: 8px; border-radius: 8px; font-weight:600; cursor:pointer;">Editar</button>
                <form action="{{ route('admin.plans.delete', $plan->id) }}" method="POST" onsubmit="return confirm('¿Eliminar el plan {{ $plan->name }}?')" style="flex:0;">
                    @csrf
                    <button type="submit" style="background: rgba(239,68,68,0.1); color: var(--danger-color); border:1px solid rgba(239,68,68,0.3); padding: 8px 12px; border-radius: 8px; font-weight:600; cursor:pointer;">Eliminar</button>
                </form>
            </div>
        </div>
    @empty
        <p style="color: var(--text-secondary);">Aún no hay planes. Crea el primero con "Nuevo plan".</p>
    @endforelse
</div>

<!-- Modal formulario -->
<div id="planModal" style="display:none; position:fixed; inset:0; background: rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:28px; width:100%; max-width:480px; max-height:90vh; overflow:auto;">
        <h2 id="planModalTitle" style="font-size:18px; font-weight:700; margin-bottom:20px;">Nuevo plan</h2>
        <form id="planForm" method="POST">
            @csrf
            @php
                $fld = 'width:100%; background: rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:8px; padding:10px 12px; font-size:14px; color: var(--text-primary); outline:none;';
                $lbl = 'display:block; font-size:13px; font-weight:500; margin-bottom:6px;';
            @endphp
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Nombre</label><input name="name" id="p_name" required style="{{ $fld }}"></div>
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Slug (opcional)</label><input name="slug" id="p_slug" style="{{ $fld }}" placeholder="se genera automático"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Precio mensual</label><input name="price_monthly" id="p_price_monthly" type="number" step="0.01" min="0" required style="{{ $fld }}"></div>
                <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Precio anual</label><input name="price_annual" id="p_price_annual" type="number" step="0.01" min="0" required style="{{ $fld }}"></div>
                <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Máx. vendedores</label><input name="max_sellers" id="p_sellers" type="number" min="0" style="{{ $fld }}" placeholder="vacío = ilimitado"></div>
                <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Máx. sucursales</label><input name="max_stores" id="p_stores" type="number" min="0" style="{{ $fld }}" placeholder="vacío = ilimitado"></div>
                <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Máx. facturas/mes</label><input name="max_monthly_invoices" id="p_invoices" type="number" min="0" style="{{ $fld }}" placeholder="vacío = ilimitado"></div>
                <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Moneda</label><input name="currency" id="p_currency" maxlength="3" value="NIO" style="{{ $fld }}"></div>
            </div>
            <div style="margin-bottom:14px;"><label style="{{ $lbl }}">Tenencia</label>
                <select name="tenancy_type" id="p_tenancy" style="{{ $fld }}">
                    <option value="shared">Compartida</option>
                    <option value="dedicated">Base de datos dedicada</option>
                </select>
            </div>
            <div style="margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="p_active" value="1" checked>
                <label for="p_active" style="font-size:13px;">Plan activo</label>
            </div>
            <div style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" id="p_featured" value="1">
                <label for="p_featured" style="font-size:13px;">Destacado en la landing (Más popular)</label>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closePlanForm()" style="flex:1; background: rgba(255,255,255,0.05); color: var(--text-secondary); border:1px solid var(--border-color); padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                <button type="submit" style="flex:1; background: var(--accent-gradient); color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    const STORE_URL = "{{ route('admin.plans.store') }}";
    function openPlanForm() {
        document.getElementById('planModalTitle').textContent = 'Nuevo plan';
        document.getElementById('planForm').reset();
        document.getElementById('planForm').action = STORE_URL;
        document.getElementById('p_active').checked = true;
        document.getElementById('planModal').style.display = 'flex';
    }
    function editPlan(plan) {
        document.getElementById('planModalTitle').textContent = 'Editar plan';
        document.getElementById('planForm').action = '/admin/plans/' + plan.id + '/update';
        document.getElementById('p_name').value = plan.name;
        document.getElementById('p_slug').value = plan.slug;
        document.getElementById('p_price_monthly').value = plan.price_monthly;
        document.getElementById('p_price_annual').value = plan.price_annual;
        document.getElementById('p_sellers').value = plan.max_sellers ?? '';
        document.getElementById('p_stores').value = plan.max_stores ?? '';
        document.getElementById('p_invoices').value = plan.max_monthly_invoices ?? '';
        document.getElementById('p_currency').value = plan.currency;
        document.getElementById('p_tenancy').value = plan.tenancy_type;
        document.getElementById('p_active').checked = !!plan.is_active;
        document.getElementById('p_featured').checked = !!plan.is_featured;
        document.getElementById('planModal').style.display = 'flex';
    }
    function closePlanForm() { document.getElementById('planModal').style.display = 'none'; }
</script>
@endsection
