<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Screenshot Service | URL to Image API</title>
    <meta name="description" content="Capture high-quality screenshots of any URL via a simple REST API. Full-size images and thumbnails, async processing, webhook support.">

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
            --warning: #f59e0b;
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

        /* Dot grid background */
        .bg-grid {
            background-image:
                radial-gradient(circle at 1px 1px, var(--border) 1px, transparent 0);
            background-size: 32px 32px;
            background-position: -1px -1px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Typography */
        h1, h2, h3 {
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        code, .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 16px 0;
            background: rgba(10, 10, 11, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-primary);
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

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }

        .nav-link:hover {
            color: var(--text-primary);
            background: var(--bg-tertiary);
        }

        /* Hero */
        .hero {
            padding: 160px 0 100px;
            position: relative;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--accent-glow);
            border: 1px solid rgba(6, 182, 212, 0.3);
            border-radius: 100px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--accent);
            margin-bottom: 24px;
            animation: fadeInUp 0.6s ease-out;
        }

        .hero-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            max-width: 800px;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        .hero h1 .accent {
            color: var(--accent);
        }

        .hero-description {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 0.9375rem;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background: var(--accent-dim);
            transform: translateY(-1px);
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

        /* Code Block */
        .code-section {
            padding: 80px 0;
        }

        .code-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .code-grid {
                grid-template-columns: 1fr;
            }
        }

        .code-block {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border);
        }

        .code-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .code-title .method {
            padding: 2px 8px;
            background: var(--accent-glow);
            color: var(--accent);
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
        }

        .code-title .method.get {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .code-dots {
            display: flex;
            gap: 6px;
        }

        .code-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--border-light);
        }

        .code-content {
            padding: 20px;
            overflow-x: auto;
        }

        .code-content pre {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8125rem;
            line-height: 1.7;
            color: var(--text-secondary);
        }

        .code-content .comment {
            color: var(--text-muted);
        }

        .code-content .string {
            color: var(--success);
        }

        .code-content .key {
            color: var(--accent);
        }

        .code-content .number {
            color: var(--warning);
        }

        /* Features */
        .features {
            padding: 80px 0;
            border-top: 1px solid var(--border);
        }

        .section-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            margin-bottom: 16px;
        }

        .section-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin-bottom: 48px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        @media (max-width: 900px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        .feature-card {
            padding: 28px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .feature-card:hover {
            border-color: var(--border-light);
            transform: translateY(-2px);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            background: var(--accent-glow);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon svg {
            width: 22px;
            height: 22px;
            color: var(--accent);
        }

        .feature-card h3 {
            font-size: 1.0625rem;
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Endpoints */
        .endpoints {
            padding: 80px 0;
            border-top: 1px solid var(--border);
        }

        .endpoint-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .endpoint {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 24px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: all 0.15s ease;
        }

        .endpoint:hover {
            border-color: var(--border-light);
        }

        .endpoint-method {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            min-width: 70px;
            text-align: center;
        }

        .endpoint-method.post {
            background: var(--accent-glow);
            color: var(--accent);
        }

        .endpoint-method.get {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .endpoint-method.delete {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .endpoint-path {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9375rem;
            color: var(--text-primary);
            flex: 1;
        }

        .endpoint-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        @media (max-width: 700px) {
            .endpoint {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .endpoint-desc {
                margin-top: 4px;
            }
        }

        /* Footer */
        .footer {
            padding: 40px 0;
            border-top: 1px solid var(--border);
        }

        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-text {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .footer-links a:hover {
            color: var(--text-primary);
        }

        @media (max-width: 600px) {
            .footer-inner {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="bg-grid">
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="/" class="logo">
                    <div class="logo-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 4h7V2H4a2 2 0 0 0-2 2v7h2V4zm16-2h-7v2h7v7h2V4a2 2 0 0 0-2-2zm0 18h-7v2h7a2 2 0 0 0 2-2v-7h-2v7zM4 13H2v7a2 2 0 0 0 2 2h7v-2H4v-7z"/>
                        </svg>
                    </div>
                    Screenshot Service
                </a>
                <nav>
                    <a href="#api" class="nav-link">API Reference</a>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-badge">API Service</div>
                <h1>Capture <span class="accent">screenshots</span> of any URL via API</h1>
                <p class="hero-description">
                    A fast, reliable REST API for capturing high-quality screenshots.
                    Get full-size images and thumbnails with async processing and webhook support.
                </p>
                <div class="hero-actions">
                    <a href="#api" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </svg>
                        View API Docs
                    </a>
                    <a href="#quick-start" class="btn btn-secondary">Quick Start</a>
                </div>
            </div>
        </section>

        <section class="code-section" id="quick-start">
            <div class="container">
                <div class="code-grid">
                    <div class="code-block">
                        <div class="code-header">
                            <div class="code-title">
                                <span class="method">POST</span>
                                Request
                            </div>
                            <div class="code-dots">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                        <div class="code-content">
                            <pre><span class="comment"># Create a screenshot</span>
curl -X POST {{ url('/api/screenshots') }} \
  -H <span class="string">"X-API-Key: your-api-key"</span> \
  -H <span class="string">"Content-Type: application/json"</span> \
  -d <span class="string">'{
    "<span class="key">url</span>": "https://example.com",
    "<span class="key">viewport_width</span>": <span class="number">1280</span>,
    "<span class="key">viewport_height</span>": <span class="number">800</span>,
    "<span class="key">thumbnail_width</span>": <span class="number">400</span>,
    "<span class="key">thumbnail_height</span>": <span class="number">300</span>
  }'</span></pre>
                        </div>
                    </div>
                    <div class="code-block">
                        <div class="code-header">
                            <div class="code-title">
                                <span class="method get">200</span>
                                Response
                            </div>
                            <div class="code-dots">
                                <span></span><span></span><span></span>
                            </div>
                        </div>
                        <div class="code-content">
                            <pre><span class="comment">// Completed screenshot response</span>
{
  <span class="key">"id"</span>: <span class="string">"9f1a2b3c-4d5e-6f7a"</span>,
  <span class="key">"status"</span>: <span class="string">"completed"</span>,
  <span class="key">"url"</span>: <span class="string">"https://example.com"</span>,
  <span class="key">"images"</span>: {
    <span class="key">"full"</span>: <span class="string">"https://s3.../full.png"</span>,
    <span class="key">"thumbnail"</span>: <span class="string">"https://s3.../thumb.png"</span>
  },
  <span class="key">"captured_at"</span>: <span class="string">"2025-01-19T..."</span>,
  <span class="key">"expires_at"</span>: <span class="string">"2025-01-20T..."</span>
}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <div class="section-label">Features</div>
                <h2 class="section-title">Built for developers</h2>
                <p class="section-description">
                    Everything you need to capture and manage screenshots at scale,
                    with a simple API that gets out of your way.
                </p>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <h3>Async Processing</h3>
                        <p>Screenshots are captured in the background. Poll for status or receive a webhook when complete.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </div>
                        <h3>Full + Thumbnails</h3>
                        <p>Get both full-resolution images and auto-generated thumbnails in a single request.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                            </svg>
                        </div>
                        <h3>Smart Caching</h3>
                        <p>Identical requests return cached results instantly. Use force_refresh to bypass when needed.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </div>
                        <h3>Webhooks</h3>
                        <p>Receive POST notifications when screenshots complete. Includes HMAC signature verification.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                        </div>
                        <h3>Custom Viewports</h3>
                        <p>Capture at any viewport size from 320px to 3840px. Desktop, tablet, or mobile views.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <h3>API Key Auth</h3>
                        <p>Secure authentication via X-API-Key header. Per-key rate limits and usage tracking.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="endpoints" id="api">
            <div class="container">
                <div class="section-label">API Reference</div>
                <h2 class="section-title">Endpoints</h2>
                <p class="section-description">
                    All endpoints require the <code class="mono">X-API-Key</code> header for authentication.
                </p>
                <div class="endpoint-list">
                    <div class="endpoint">
                        <span class="endpoint-method post">POST</span>
                        <span class="endpoint-path">/api/screenshots</span>
                        <span class="endpoint-desc">Create a new screenshot request</span>
                    </div>
                    <div class="endpoint">
                        <span class="endpoint-method get">GET</span>
                        <span class="endpoint-path">/api/screenshots/{id}</span>
                        <span class="endpoint-desc">Check status and retrieve image URLs</span>
                    </div>
                    <div class="endpoint">
                        <span class="endpoint-method delete">DELETE</span>
                        <span class="endpoint-path">/api/screenshots/{id}</span>
                        <span class="endpoint-desc">Invalidate a cached screenshot</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-inner">
                <div class="footer-text">Screenshot Service API</div>
                <div class="footer-links">
                    <a href="#api">API Reference</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
