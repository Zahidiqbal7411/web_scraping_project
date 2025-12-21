<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: hsl(220, 85%, 50%);
            --primary-light: hsl(220, 85%, 96%);
            --secondary: hsl(200, 70%, 50%);
            --accent: hsl(340, 70%, 55%);
            --bg: hsl(220, 20%, 97%);
            --card-bg: hsl(0, 0%, 100%);
            --card-border: hsl(0, 0%, 88%);
            --text-primary: hsl(0, 0%, 15%);
            --text-secondary: hsl(0, 0%, 45%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --success: hsl(142, 70%, 45%);
            --error: hsl(0, 84%, 60%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--card-bg);
            border-right: 1px solid var(--card-border);
            flex-shrink: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            left: 0;
            top: 0;
            z-index: 50;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar-toggle {
            padding: 1rem;
            display: flex;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--card-border);
        }

        .sidebar-menu {
            padding: 1rem 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .sidebar-item {
            padding: 0.875rem;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-item span {
            display: none;
        }

        .sidebar-item:hover, .sidebar-item.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .logout-btn {
            width: 100%;
            border: none;
            background: none;
            font-family: inherit;
            font-size: inherit;
            cursor: pointer;
            text-align: left;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            margin-left: 260px; /* Width of sidebar */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .content-area.sidebar-collapsed {
            margin-left: 70px;
        }

        /* Navbar (Header) */
        .navbar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--card-border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }

        /* Main Content Body */
        .main-content {
            padding: 2rem;
            flex: 1;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        /* Footer */
        .footer {
            background: var(--card-bg);
            border-top: 1px solid var(--card-border);
            padding: 1.5rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }

            .content-area {
                margin-left: 0;
            }
        }
    </style>
    @yield('styles')
</head>
<body>
    <div class="app-wrapper">
        @include('layouts.sidebar')

        <div class="content-area">
            @include('layouts.header')

            <main class="main-content">
                @if (isset($header))
                    <div class="mb-6">
                        {{ $header }}
                    </div>
                @endif
                
                @yield('content')
                
                {{ $slot ?? '' }}
            </main>

            @include('layouts.footer')
        </div>
    </div>
    
    @yield('scripts')
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const contentArea = document.querySelector('.content-area');
            
            sidebar.classList.toggle('collapsed');
            contentArea.classList.toggle('sidebar-collapsed');
            
            // Store preference in localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        // Apply stored preference on load
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const contentArea = document.querySelector('.content-area');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                contentArea.classList.add('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>
