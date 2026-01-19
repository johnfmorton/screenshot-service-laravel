<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} - Screenshot Service</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-primary: #0a0a0b;
            --bg-secondary: #111113;
            --bg-tertiary: #18181b;
            --border: #27272a;
            --border-light: #3f3f46;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --accent: #06b6d4;
            --accent-dim: #0891b2;
            --accent-glow: rgba(6, 182, 212, 0.15);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.15);
            --warning: #f59e0b;
            --warning-glow: rgba(245, 158, 11, 0.15);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        code, .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
            text-decoration: none;
        }

        .sidebar-logo:hover {
            text-decoration: none;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dim) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon svg {
            width: 18px;
            height: 18px;
            color: var(--bg-primary);
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0 8px;
            margin-bottom: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--text-secondary);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .nav-item:hover {
            color: var(--text-primary);
            background: var(--bg-tertiary);
            text-decoration: none;
        }

        .nav-item.active {
            color: var(--accent);
            background: var(--accent-glow);
        }

        .nav-item svg {
            width: 18px;
            height: 18px;
            opacity: 0.7;
        }

        .nav-item.active svg {
            opacity: 1;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--bg-tertiary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }

        .content-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-secondary);
        }

        .content-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .content-header p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .content-body {
            padding: 32px;
        }

        /* Common UI Components */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn:hover {
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background: var(--accent-dim);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--border-light);
        }

        .btn-danger {
            background: var(--danger-glow);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8125rem;
        }

        .btn svg {
            width: 16px;
            height: 16px;
        }

        /* Cards */
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2 {
            font-size: 1rem;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--bg-tertiary);
        }

        td {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        tr:hover td {
            background: var(--bg-tertiary);
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 10px 14px;
            font-size: 0.875rem;
            font-family: inherit;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            transition: all 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-hint {
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }

        select.form-input {
            cursor: pointer;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: var(--success-glow);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: var(--danger-glow);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-warning {
            background: var(--warning-glow);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 6px;
        }

        .badge-success {
            background: var(--success-glow);
            color: var(--success);
        }

        .badge-warning {
            background: var(--warning-glow);
            color: var(--warning);
        }

        .badge-danger {
            background: var(--danger-glow);
            color: var(--danger);
        }

        .badge-info {
            background: var(--accent-glow);
            color: var(--accent);
        }

        .badge-muted {
            background: var(--bg-tertiary);
            color: var(--text-muted);
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }

        .stat-card-label {
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .stat-card-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-card-accent .stat-card-value {
            color: var(--accent);
        }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }

        .pagination-link {
            padding: 8px 14px;
            font-size: 0.875rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .pagination-link:hover {
            background: var(--border);
            color: var(--text-primary);
            text-decoration: none;
        }

        .pagination-link.active {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--bg-primary);
        }

        .pagination-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-bar .form-input {
            width: auto;
            min-width: 180px;
        }

        /* URL truncate */
        .url-truncate {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
        }

        /* Key display */
        .key-display {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8125rem;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            word-break: break-all;
        }

        /* Multi-select */
        .checkbox-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
        }

        .checkbox-list label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .checkbox-list label:hover {
            background: var(--bg-tertiary);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .content-body {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-bar {
                flex-direction: column;
            }

            .filter-bar .form-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <x-admin.navigation />

        <main class="main-content">
            <div class="content-header">
                <h1>{{ $title ?? 'Dashboard' }}</h1>
                @if(isset($description))
                    <p>{{ $description }}</p>
                @endif
            </div>

            <div class="content-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <div>
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
