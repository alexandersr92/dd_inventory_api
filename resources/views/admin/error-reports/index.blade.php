@extends('admin.layout')

@section('title', 'Reportes de error')

@section('content')
@php
    $fld = 'width:100%; background: rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:8px; padding:10px 12px; font-size:14px; color: var(--text-primary); outline:none;';
@endphp

<div class="header-section" style="margin-bottom:28px;">
    <div>
        <h1 style="font-size:28px; font-weight:800; letter-spacing:-0.5px;">Reportes de error</h1>
        <p style="color: var(--text-secondary); margin-top:8px;">Problemas reportados por el equipo desde el panel, con capturas.</p>
    </div>
    @if($openCount > 0)
        <span style="background:#F59E0B; color:#000; padding:6px 14px; border-radius:20px; font-weight:700; font-size:13px;">{{ $openCount }} abierto{{ $openCount === 1 ? '' : 's' }}</span>
    @endif
</div>

@if(session('success'))
    <div style="background: rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:10px; padding:14px 18px; margin-bottom:24px; color: var(--success-color); font-size:14px; font-weight:500;">{{ session('success') }}</div>
@endif

@forelse($reports as $r)
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:20px; margin-bottom:16px; {{ $r->status === 'resolved' ? 'opacity:0.6;' : '' }}">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px; align-items:flex-start;">
            <div style="flex:1; min-width:240px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <span style="font-size:11px; padding:3px 10px; border-radius:6px; font-weight:600; {{ $r->status === 'open' ? 'background:rgba(245,158,11,0.15); color:#FBBF24;' : 'background:rgba(16,185,129,0.12); color:#34D399;' }}">
                        {{ $r->status === 'open' ? 'Abierto' : 'Resuelto' }}
                    </span>
                    @if($r->source === 'tenant')
                        <span style="font-size:10px; padding:3px 8px; border-radius:6px; background:rgba(99,102,241,0.15); color:#818CF8; font-weight:600;">Cliente</span>
                        <strong style="font-size:14px;">{{ $r->organization_name ?? 'Organización' }}</strong>
                        <span style="font-size:12px; color: var(--text-secondary);">· {{ $r->reporter_name }} ({{ $r->reporter_email }})</span>
                    @else
                        <span style="font-size:10px; padding:3px 8px; border-radius:6px; background:rgba(255,255,255,0.06); color:var(--text-secondary); font-weight:600;">Root</span>
                        <strong style="font-size:14px;">{{ $r->admin_name ?? 'Desconocido' }}</strong>
                    @endif
                    <span style="font-size:12px; color: var(--text-secondary);">· {{ $r->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <p style="font-size:14px; white-space:pre-line; margin-bottom:8px;">{{ $r->message }}</p>
                @if($r->page_url)
                    <p style="font-size:12px; color: var(--text-secondary); font-family:monospace;">📍 {{ $r->page_url }}</p>
                @endif
                @if($r->resolution_notes)
                    <p style="font-size:12px; color:#34D399; margin-top:6px;">✔ {{ $r->resolution_notes }}</p>
                @endif
            </div>
            @if($r->screenshot_path)
                <a href="{{ route('admin.error-reports.screenshot', $r->id) }}" target="_blank" style="display:block; flex-shrink:0;">
                    <img src="{{ route('admin.error-reports.screenshot', $r->id) }}" alt="captura" style="max-width:160px; max-height:110px; border-radius:8px; border:1px solid var(--border-color);">
                </a>
            @endif
        </div>
        <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
            @if($r->status === 'open')
                <button onclick="document.getElementById('resolve-{{ $r->id }}').style.display='flex'" style="background: var(--success-gradient); color:#fff; border:none; padding:8px 16px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">Marcar resuelto</button>
            @endif
            <form action="{{ route('admin.error-reports.destroy', $r->id) }}" method="POST" onsubmit="return confirm('¿Eliminar este reporte?')">
                @csrf
                <button type="submit" style="background: rgba(239,68,68,0.1); color: var(--danger-color); border:1px solid rgba(239,68,68,0.3); padding:8px 16px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">Eliminar</button>
            </form>
        </div>

        <!-- Modal resolver -->
        <div id="resolve-{{ $r->id }}" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
            <div style="background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:26px; width:100%; max-width:420px;">
                <h2 style="font-size:17px; font-weight:700; margin-bottom:14px;">Marcar como resuelto</h2>
                <form action="{{ route('admin.error-reports.resolve', $r->id) }}" method="POST">
                    @csrf
                    <textarea name="resolution_notes" style="{{ $fld }} min-height:80px; resize:vertical;" placeholder="Nota de resolución (opcional)"></textarea>
                    <div style="display:flex; gap:10px; margin-top:16px;">
                        <button type="button" onclick="document.getElementById('resolve-{{ $r->id }}').style.display='none'" style="flex:1; background:rgba(255,255,255,0.05); color:var(--text-secondary); border:1px solid var(--border-color); padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                        <button type="submit" style="flex:1; background:var(--success-gradient); color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@empty
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:40px; text-align:center; color: var(--text-secondary);">
        No hay reportes de error. 🎉
    </div>
@endforelse

<div style="margin-top:20px;">{{ $reports->links() }}</div>
@endsection
