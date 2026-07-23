<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Correo verificado — DipleBill</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 24px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }
        .badge {
            width: 72px; height: 72px;
            margin: 0 auto 24px;
            border-radius: 999px;
            background: rgba(34,197,94,.12);
            border: 1px solid rgba(34,197,94,.35);
            display: flex; align-items: center; justify-content: center;
        }
        .badge svg { width: 38px; height: 38px; stroke: #22c55e; }
        h1 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 10px; }
        p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin-bottom: 28px; }
        .btn {
            display: inline-block;
            background: #4f46e5;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 14px 28px;
            border-radius: 12px;
            transition: background .15s;
        }
        .btn:hover { background: #6366f1; }
        .brand { margin-top: 28px; font-size: 12px; color: #64748b; }
        .brand b { color: #8fb2f9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
        </div>
        @if ($alreadyVerified)
            <h1>Tu correo ya estaba verificado</h1>
            <p>No hay nada más que hacer. Ya puedes iniciar sesión en DipleBill con tu cuenta.</p>
        @else
            <h1>¡Correo verificado!</h1>
            <p>Gracias por confirmar tu correo. Tu cuenta de DipleBill quedó asegurada y ahora recibirás los avisos de tu licencia y pagos.</p>
        @endif
        <a class="btn" href="{{ $appUrl }}">Ir a DipleBill</a>
        <div class="brand"><b>Diple</b>Bill · Factura. Gestiona. Crece.</div>
    </div>
</body>
</html>
