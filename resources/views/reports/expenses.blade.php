@extends('reports.layout', ['title' => 'Reporte de Gastos y Compras'])

@section('content')
    <h3 class="section-title">Detalle de Egresos</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha / Comprobante</th>
                <th>Proveedor</th>
                <th>Clasificación / Centro de Costos</th>
                <th>Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        {{ $item['date'] }}<br>
                        <small style="color: #666;">Ref: {{ $item['reference'] ?? 'N/A' }}</small>
                    </td>
                    <td>{{ $item['supplier'] }}</td>
                    <td>
                        <small style="color: #666;">Categoría: N/A</small><br>
                        <small style="color: #666;">C. Costos: N/A</small>
                    </td>
                    <td>{{ $currency }}{{ number_format($item['total'], 2) }}</td>
                    <td>
                        @if($item['status'] === 'completed')
                            <span style="color: green; font-weight: bold;">Pagado (Completado)</span>
                        @else
                            <span style="color: red;">Cuenta por Pagar / {{ ucfirst($item['status']) }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">No hay gastos en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
