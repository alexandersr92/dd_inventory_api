@extends('admin.layout')

@section('title', 'Auditoría')

@section('content')
<div class="header-section" style="margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.5px;">Auditoría</h1>
        <p style="color: var(--text-secondary); margin-top: 8px;">Registro de acciones sensibles realizadas por los administradores.</p>
    </div>
</div>

<div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
            <tr style="text-align: left; color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                <th style="padding: 16px 20px; border-bottom: 1px solid var(--border-color);">Fecha</th>
                <th style="padding: 16px 20px; border-bottom: 1px solid var(--border-color);">Administrador</th>
                <th style="padding: 16px 20px; border-bottom: 1px solid var(--border-color);">Acción</th>
                <th style="padding: 16px 20px; border-bottom: 1px solid var(--border-color);">Detalle</th>
                <th style="padding: 16px 20px; border-bottom: 1px solid var(--border-color);">IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td style="padding: 14px 20px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); white-space: nowrap;">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td style="padding: 14px 20px; border-bottom: 1px solid var(--border-color);">{{ $log->admin_name ?? '—' }}</td>
                    <td style="padding: 14px 20px; border-bottom: 1px solid var(--border-color);">
                        <span style="font-family: monospace; font-size: 12px; background: rgba(99,102,241,0.12); color: #818CF8; padding: 3px 8px; border-radius: 6px;">{{ $log->action }}</span>
                    </td>
                    <td style="padding: 14px 20px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">{{ $log->description }}</td>
                    <td style="padding: 14px 20px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-family: monospace; font-size: 12px;">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding: 32px; text-align: center; color: var(--text-secondary);">Aún no hay acciones registradas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    {{ $logs->links() }}
</div>
@endsection
