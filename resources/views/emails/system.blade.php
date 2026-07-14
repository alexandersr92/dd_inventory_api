<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: none;
            -ms-text-size-adjust: none;
        }
        .wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #090D16;
            padding: 30px;
            text-align: center;
        }
        .logo-icon {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            background: linear-gradient(135deg, #6366F1, #3B82F6);
            border-radius: 10px;
            font-weight: 700;
            font-size: 22px;
            color: #ffffff;
            text-align: center;
            vertical-align: middle;
        }
        .logo-text {
            display: inline-block;
            font-weight: 700;
            font-size: 22px;
            color: #ffffff;
            margin-left: 10px;
            vertical-align: middle;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 40px;
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
        }
        .content h1, .content h2, .content h3 {
            color: #111827;
            margin-top: 0;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px 40px;
            text-align: center;
            font-size: 13px;
            color: #9CA3AF;
            border-top: 1px solid #f3f4f6;
        }
        .footer a {
            color: #6366F1;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <div class="logo-icon">D</div>
                <div class="logo-text">DipleBill</div>
            </div>
            <div class="content">
                {!! $body !!}
            </div>
            <div class="footer">
                <p style="margin: 0;">Este correo electrónico fue generado de forma automática por la plataforma.</p>
                <p style="margin: 6px 0 0 0;">&copy; {{ date('Y') }} DipleBill. Todos los derechos reservados.</p>
            </div>
        </div>
    </div>
</body>
</html>
