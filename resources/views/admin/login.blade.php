<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Superadmin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Login Styles -->
    <style>
        :root {
            --bg-primary: #07090E;
            --bg-secondary: rgba(17, 23, 38, 0.6);
            --text-primary: #F3F4F6;
            --text-secondary: #9CA3AF;
            --accent-gradient: linear-gradient(135deg, #6366F1, #3B82F6);
            --border-color: rgba(255, 255, 255, 0.06);
            --error-color: #EF4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Decorative blur elements */
        .blur-orb-1 {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
            top: -100px;
            left: -100px;
            border-radius: 50%;
            pointer-events: none;
        }

        .blur-orb-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, rgba(0, 0, 0, 0) 70%);
            bottom: -150px;
            right: -150px;
            border-radius: 50%;
            pointer-events: none;
        }

        .login-card {
            background: var(--bg-secondary);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 48px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            z-index: 5;
            position: relative;
        }

        .logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 32px;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: var(--accent-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 24px;
            color: #FFF;
            box-shadow: 0 8px 16px -4px rgba(99, 102, 241, 0.5);
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        .form-control {
            width: 100%;
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 16px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: #6366F1;
            background-color: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .btn-submit {
            width: 100%;
            background: var(--accent-gradient);
            border: none;
            color: #FFF;
            padding: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -4px rgba(99, 102, 241, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-icon {
            flex-shrink: 0;
        }
    </style>
</head>
<body>

    <div class="blur-orb-1"></div>
    <div class="blur-orb-2"></div>

    <div class="login-card">
        <div class="logo-section">
            <div class="logo-icon">D</div>
            <div class="logo-text">Plataforma Admin</div>
        </div>

        @if ($errors->any())
            <div class="error-message">
                <svg class="error-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>Credenciales inválidas o cuenta inactiva.</span>
            </div>
        @endif

        <form action="{{ route('admin.login') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="admin@myplatform.com" required autofocus>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit">Ingresar</button>
        </form>
    </div>

</body>
</html>
