<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 10px;
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
            font-size: 20px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 11px;
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
            padding: 3px 0;
            color: #555;
        }
        .info-table td {
            padding: 3px 0;
            font-weight: bold;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-size: 11px;
        }
        .data-table th {
            background-color: #f4f4f4;
            color: #333;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .section-title {
            color: #0056b3;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $title ?? 'Reporte' }}</h1>
        <p>Generado el: {{ \Carbon\Carbon::parse($report->created_at)->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <th>Nombre del Reporte:</th>
                <td>{{ $report->name }}</td>
            </tr>
            <tr>
                <th>Generado por:</th>
                <td>{{ $report->user ? $report->user->name : 'N/A' }}</td>
            </tr>
            @if(isset($filters['date_from']) || isset($filters['date_to']))
            <tr>
                <th>Rango de Fechas:</th>
                <td>
                    {{ $filters['date_from'] ?? 'Inicio' }} al {{ $filters['date_to'] ?? 'Hoy' }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    @yield('content')

    <div class="footer">
        Este documento es generado de forma automática por el sistema.
    </div>

</body>
</html>
