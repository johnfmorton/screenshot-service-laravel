<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Screenshot Service Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dim) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
            color: var(--bg-primary);
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .login-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            font-size: 0.9375rem;
            font-family: inherit;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 10px;
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

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .form-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }

        .btn {
            width: 100%;
            padding: 12px 24px;
            font-size: 0.9375rem;
            font-weight: 500;
            font-family: inherit;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn:hover {
            background: var(--accent-dim);
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 0.875rem;
            background: var(--danger-glow);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--text-muted);
            font-size: 0.875rem;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .back-link:hover {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <div class="logo-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M4 4h7V2H4a2 2 0 0 0-2 2v7h2V4zm16-2h-7v2h7v7h2V4a2 2 0 0 0-2-2zm0 18h-7v2h7a2 2 0 0 0 2-2v-7h-2v7zM4 13H2v7a2 2 0 0 0 2 2h7v-2H4v-7z"/>
                    </svg>
                </div>
            </div>
            <h1>Admin Login</h1>
            <p>Sign in to access the admin panel</p>
        </div>

        <div class="login-card">
            @if($errors->any())
                <div class="alert">
                    @foreach($errors->all() as $error)
                        {{ $error }}
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        value="{{ old('email') }}"
                        placeholder="admin@example.com"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn">Sign In</button>
            </form>
        </div>

        <a href="/" class="back-link">Back to homepage</a>
    </div>
</body>
</html>
