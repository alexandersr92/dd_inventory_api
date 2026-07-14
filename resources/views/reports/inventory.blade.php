@extends('reports.layout', ['title' => 'Reporte de Inventario'])

@section('content')
    <h3 class="section-title">Valoración y Existencias</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>SKU / Producto</th>
                <th>Categoría</th>
                <th>Stock / Mínimo</th>
                <th>Costo / Precio</th>
                <th>Valor Total</th>
                <th>Último Mov.</th>
                <th>Alertas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        {{ $item['sku'] ?? 'N/A' }}<br>
                        <strong>{{ $item['name'] }}</strong>
                    </td>
                    <td>{{ $item['category'] }}</td>
                    <td>
                        <span style="color: {{ $item['is_low_stock'] ? 'red' : 'green' }}; font-weight: bold;">
                            {{ $item['stock'] }}
                        </span> 
                        / {{ $item['min_stock'] }}
                    </td>
                    <td>
                        C: {{ $currency }}{{ number_format($item['cost'], 2) }}<br>
                        V: {{ $currency }}{{ number_format($item['price'], 2) }}
                    </td>
                    <td>{{ $currency }}{{ number_format($item['total_value'], 2) }}</td>
                    <td>{{ $item['last_movement'] ?? 'N/A' }}</td>
                    <td>
                        @if($item['is_low_stock'])
                            <span style="color: red; font-weight: bold;">[!] Stock Bajo</span>
                        @else
                            <span style="color: green;">OK</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No hay productos en este inventario.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
