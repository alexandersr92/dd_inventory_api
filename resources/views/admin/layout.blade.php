<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - @yield('title', 'Platform Manager')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Base Styles -->
    <style>
        :root {
            --bg-primary: #090D16;
            --bg-secondary: #111726;
            --bg-tertiary: #192239;
            --text-primary: #F3F4F6;
            --text-secondary: #9CA3AF;
            --accent-gradient: linear-gradient(135deg, #6366F1, #3B82F6);
            --accent-color: #6366F1;
            --border-color: rgba(255, 255, 255, 0.08);
            --danger-color: #EF4444;
            --success-color: #10B981;
            --success-gradient: linear-gradient(135deg, #10B981, #059669);
            --danger-gradient: linear-gradient(135deg, #EF4444, #DC2626);
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
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background-color: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 24px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 10;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--accent-gradient);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #FFF;
        }

        .logo-text {
            font-weight: 700;
            font-size: 20px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
            list-style: none;
            flex-grow: 1;
        }

        .nav-link a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link.active a, .nav-link a:hover {
            color: var(--text-primary);
            background-color: rgba(255, 255, 255, 0.04);
            box-shadow: inset 3px 0 0 var(--accent-color);
        }

        .sidebar-footer {
            margin-top: auto;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            padding: 12px;
            text-align: left;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            color: var(--danger-color);
            background-color: rgba(239, 68, 68, 0.08);
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 260px;
            flex-grow: 1;
            min-height: 100vh;
            padding: 40px;
            position: relative;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .header-title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Notification */
        .toast-notification {
            position: fixed;
            top: 24px;
            right: 24px;
            background: var(--accent-gradient);
            color: #FFF;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
            animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            font-weight: 500;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%) translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateX(0) translateY(0);
                opacity: 1;
            }
        }

        @yield('styles')
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-container">
            <div class="logo-icon">D</div>
            <div class="logo-text">DipleBill</div>
        </div>
        
        <ul class="nav-links">
            <li class="nav-link {{ request('tab', 'dashboard') === 'dashboard' ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard', ['tab' => 'dashboard']) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                    Inicio
                </a>
            </li>
            <li class="nav-link {{ request('tab') === 'clients' ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard', ['tab' => 'clients']) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Clientes
                </a>
            </li>
            <li class="nav-link {{ request('tab') === 'admins' ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard', ['tab' => 'admins']) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Usuarios Root
                </a>
            </li>
            <li class="nav-link {{ request('tab') === 'backups' ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard', ['tab' => 'backups']) }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-2.239 10-5s-4.477-5-10-5S2 14.761 2 17.5s4.477 5 10 5z"></path><path d="M22 12c0 2.761-4.477 5-10 5S2 14.761 2 12"></path><path d="M22 6.5c0 2.761-4.477 5-10 5S2 9.261 2 6.5"></path></svg>
                    Copias de Seguridad
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
                <a href="{{ route('admin.plans.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12"></path><path d="M2 7h20v5H2z"></path><path d="M12 22V7"></path><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                    Planes
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                <a href="{{ route('admin.payments.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Pagos
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                <a href="{{ route('admin.audit.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    Auditoría
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.emails.*') ? 'active' : '' }}">
                <a href="{{ route('admin.emails.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    Correos
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                <a href="{{ route('admin.notifications.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                    Notificaciones
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.error-reports.*') ? 'active' : '' }}">
                <a href="{{ route('admin.error-reports.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 2 1.88 1.88"></path><path d="M14.12 3.88 16 2"></path><path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"></path><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6"></path><path d="M12 20v-9"></path><path d="M6.53 9C4.6 8.8 3 7.1 3 5"></path><path d="M6 13H2"></path><path d="M3 21c0-2.1 1.7-3.9 3.8-4"></path><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"></path><path d="M22 13h-4"></path><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"></path></svg>
                    Reportes de error
                </a>
            </li>
            <li class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <a href="{{ route('admin.settings.index') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Configuración Global
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <form action="{{ route('admin.logout') }}" method="POST" id="logout-form">
                @csrf
                <button type="submit" class="logout-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        @if(session('success'))
            <div class="toast-notification" id="toast">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                {{ session('success') }}
            </div>
            <script>
                setTimeout(() => {
                    const toast = document.getElementById('toast');
                    if (toast) {
                        toast.style.transition = 'opacity 0.5s ease';
                        toast.style.opacity = '0';
                        setTimeout(() => toast.remove(), 500);
                    }
                }, 4000);
            </script>
        @endif

        @yield('content')
    </div>

</body>
</html>
