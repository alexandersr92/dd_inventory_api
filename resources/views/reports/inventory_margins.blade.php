@extends('reports.layout', ['title' => 'Reporte de Inventario y Márgenes'])

@section('content')
    <h3 class="section-title">Detalle de Inventario, Ventas y Rentabilidad</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Producto / Códigos</th>
                <th style="width: 14%;">Categoría</th>
                <th style="width: 8%; text-align: center;">Stock</th>
                <th style="width: 13%; text-align: center;">Vendido (Cant / Total)</th>
                <th style="width: 10%; text-align: right;">Precio</th>
                <th style="width: 10%; text-align: right;">Costo</th>
                <th style="width: 10%; text-align: right;">Margen ($)</th>
                <th style="width: 10%; text-align: right;">Margen (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        <strong>{{ $item['name'] }}</strong><br>
                        <span style="color: #666; font-size: 10px;">Cód: {{ $item['sku'] ?? 'N/A' }}</span>
                        @if(!empty($item['barcode']))
                            <br><span style="color: #666; font-size: 10px;">Barras: {{ $item['barcode'] }}</span>
                        @endif
                    </td>
                    <td>{{ $item['category'] }}</td>
                    <td style="text-align: center; font-weight: bold;">
                        {{ $item['stock'] }}
                    </td>
                    <td style="text-align: center;">
                        <span style="font-weight: bold;">{{ $item['sold_qty'] }} uds</span><br>
                        <span style="color: #555; font-size: 10px;">{{ $currency }}{{ number_format($item['sold_amount'], 2) }}</span>
                    </td>
                    <td style="text-align: right;">{{ $currency }}{{ number_format($item['price'], 2) }}</td>
                    <td style="text-align: right;">{{ $currency }}{{ number_format($item['cost'], 2) }}</td>
                    <td style="text-align: right; color: {{ $item['margin_amount'] < 0 ? '#d9534f' : '#28a745' }};">
                        {{ $currency }}{{ number_format($item['margin_amount'], 2) }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: {{ $item['margin_percent'] < 0 ? '#d9534f' : '#28a745' }};">
                        {{ number_format($item['margin_percent'], 2) }}%
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 15px;">No hay productos en este inventario.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
