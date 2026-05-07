<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #0056b3;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 12px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table th {
            text-align: left;
            width: 30%;
            padding: 5px 0;
            color: #555;
        }
        .info-table td {
            padding: 5px 0;
            font-weight: bold;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .data-table th {
            background-color: #f4f4f4;
            color: #333;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Reporte de {{ ucfirst($report->type) }}</h1>
        <p>Generado el: {{ \Carbon\Carbon::parse($report->created_at)->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <th>Nombre del Reporte:</th>
                <td>{{ $report->name }}</td>
            </tr>
            <tr>
                <th>Generado por (ID):</th>
                <td>{{ $report->user_id }}</td>
            </tr>
            @if(isset($filters['date_from']) || isset($filters['date_to']))
            <tr>
                <th>Rango de Fechas:</th>
                <td>
                    {{ $filters['date_from'] ?? 'Inicio' }} - {{ $filters['date_to'] ?? 'Hoy' }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Contenido dinámico principal -->
    <h3>Datos del Reporte</h3>
    
    @if(isset($items) && count($items) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    @foreach(array_keys(is_object($items[0]) ? get_object_vars($items[0]) : $items[0]) as $key)
                        <th>{{ strtoupper(str_replace('_', ' ', $key)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        @foreach(is_object($item) ? get_object_vars($item) : $item as $value)
                            <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; color: #888; padding: 20px; background-color: #f9f9f9; border: 1px dashed #ccc;">
            No se encontraron datos detallados para este reporte con los filtros seleccionados, o el detalle aún no ha sido implementado.
        </p>
    @endif

    <div class="footer">
        Este documento es generado de forma automática por el sistema de inventario.
    </div>

</body>
</html>
