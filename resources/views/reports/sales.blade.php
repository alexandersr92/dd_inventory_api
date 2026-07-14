@extends('reports.layout', ['title' => 'Reporte de Ventas'])

@section('content')

    {{-- Rentabilidad General --}}
    <h3 class="section-title">Rentabilidad Global</h3>
    <table class="data-table">
        <tr>
            <th>Ingresos Totales (Venta)</th>
            <th>Costo de Mercancía</th>
            <th>Margen Bruto ($)</th>
            <th>Margen Bruto (%)</th>
        </tr>
        <tr>
            <td>{{ $currency }}{{ number_format($items['rentabilidad']['ingresos'], 2) }}</td>
            <td>{{ $currency }}{{ number_format($items['rentabilidad']['costo'], 2) }}</td>
            <td>{{ $currency }}{{ number_format($items['rentabilidad']['margen_dinero'], 2) }}</td>
            <td>{{ number_format($items['rentabilidad']['margen_porcentaje'], 2) }}%</td>
        </tr>
    </table>

    {{-- Ventas por Mes --}}
    <h3 class="section-title">Ventas por Mes</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Mes</th>
                <th>Total Facturas</th>
                <th>Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items['ventas_por_mes'] as $venta)
                <tr>
                    <td>{{ $venta['mes'] }}</td>
                    <td>{{ $venta['total_facturas'] }}</td>
                    <td>{{ $currency }}{{ number_format($venta['ingresos_totales'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align: center;">Sin ventas</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Top Productos --}}
    <h3 class="section-title">Top Productos Más Vendidos</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad Vendida</th>
                <th>Ingresos Generados</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items['top_productos'] as $prod)
                <tr>
                    <td>{{ $prod['name'] }}</td>
                    <td>{{ $prod['total_vendido'] }} uds</td>
                    <td>{{ $currency }}{{ number_format($prod['ingresos'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align: center;">Sin datos</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Ventas por Vendedor / Canal --}}
    <h3 class="section-title">Ventas por Vendedor y Canal</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Vendedor</th>
                <th>Canal</th>
                <th>Total Ventas</th>
                <th>Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items['ventas_por_vendedor'] as $ven)
                <tr>
                    <td>{{ $ven['vendedor'] }}</td>
                    <td>Físico (Tienda)</td>
                    <td>{{ $ven['total_ventas'] }}</td>
                    <td>{{ $currency }}{{ number_format($ven['ingresos'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align: center;">Sin datos</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
