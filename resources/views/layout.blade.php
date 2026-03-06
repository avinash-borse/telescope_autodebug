<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AutoDebug Dashboard — TelescopeAI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ──────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-primary: #0a0e1a;
            --bg-secondary: #111827;
            --bg-card: #1a1f2e;
            --bg-card-hover: #222840;
            --bg-input: #151b2e;
            --border: #2a3150;
            --border-accent: #3b4570;
            --text-primary: #e8ecf4;
            --text-secondary: #8b95b0;
            --text-muted: #5a6380;
            --accent-blue: #3b82f6;
            --accent-blue-glow: rgba(59, 130, 246, 0.25);
            --accent-purple: #8b5cf6;
            --accent-purple-glow: rgba(139, 92, 246, 0.2);
            --accent-green: #10b981;
            --accent-green-glow: rgba(16, 185, 129, 0.2);
            --accent-amber: #f59e0b;
            --accent-amber-glow: rgba(245, 158, 11, 0.2);
            --accent-red: #ef4444;
            --accent-red-glow: rgba(239, 68, 68, 0.2);
            --accent-cyan: #06b6d4;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-xs: 6px;
            --shadow-card: 0 4px 24px rgba(0, 0, 0, 0.3);
            --shadow-glow: 0 0 30px rgba(59, 130, 246, 0.1);
            --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html { font-size: 14px; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        a { color: var(--accent-blue); text-decoration: none; transition: color var(--transition); }
        a:hover { color: #60a5fa; }

        /* ── Layout ────────────────────────────────────────────────── */
        .app-shell { display: flex; flex-direction: column; min-height: 100vh; }

        .top-bar {
            position: sticky; top: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem; height: 64px;
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(16px) saturate(180%);
            border-bottom: 1px solid var(--border);
        }

        .top-bar .logo { display: flex; align-items: center; gap: 0.75rem; font-weight: 700; font-size: 1.15rem; letter-spacing: -0.02em; }
        .top-bar .logo .icon { width: 32px; height: 32px; background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .top-bar .badge { display: inline-flex; align-items: center; padding: 0.2rem 0.6rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; background: var(--accent-purple-glow); color: var(--accent-purple); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 20px; }

        .top-bar nav { display: flex; align-items: center; gap: 1.25rem; }
        .top-bar nav a { color: var(--text-secondary); font-weight: 500; font-size: 0.9rem; padding: 0.4rem 0.8rem; border-radius: var(--radius-xs); transition: all var(--transition); }
        .top-bar nav a:hover, .top-bar nav a.active { color: var(--text-primary); background: rgba(59, 130, 246, 0.1); }

        .main-content { flex: 1; padding: 2rem; max-width: 1440px; margin: 0 auto; width: 100%; }

        /* ── Cards ─────────────────────────────────────────────────── */
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-card); overflow: hidden; transition: border-color var(--transition), box-shadow var(--transition); }
        .card:hover { border-color: var(--border-accent); }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1rem; font-weight: 600; letter-spacing: -0.01em; }
        .card-body { padding: 1.5rem; }

        /* ── Stats Grid ────────────────────────────────────────────── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }

        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; transition: all var(--transition); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 3px 3px 0 0; }
        .stat-card.blue::before { background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan)); }
        .stat-card.green::before { background: linear-gradient(90deg, var(--accent-green), #34d399); }
        .stat-card.purple::before { background: linear-gradient(90deg, var(--accent-purple), #a78bfa); }
        .stat-card.amber::before { background: linear-gradient(90deg, var(--accent-amber), #fbbf24); }
        .stat-card.red::before { background: linear-gradient(90deg, var(--accent-red), #f87171); }
        .stat-card.cyan::before { background: linear-gradient(90deg, var(--accent-cyan), #22d3ee); }
        .stat-card:hover { border-color: var(--border-accent); transform: translateY(-2px); box-shadow: var(--shadow-card), var(--shadow-glow); }

        .stat-label { font-size: 0.78rem; font-weight: 500; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; }
        .stat-value { font-size: 2rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
        .stat-card.blue .stat-value { color: var(--accent-blue); }
        .stat-card.green .stat-value { color: var(--accent-green); }
        .stat-card.purple .stat-value { color: var(--accent-purple); }
        .stat-card.amber .stat-value { color: var(--accent-amber); }
        .stat-card.red .stat-value { color: var(--accent-red); }
        .stat-card.cyan .stat-value { color: var(--accent-cyan); }

        /* ── Buttons ───────────────────────────────────────────────── */
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 500; border: 1px solid transparent; border-radius: var(--radius-xs); cursor: pointer; transition: all var(--transition); font-family: inherit; text-decoration: none; }
        .btn-primary { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); }
        .btn-primary:hover { background: #2563eb; box-shadow: 0 0 16px var(--accent-blue-glow); color: #fff; }
        .btn-secondary { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-secondary:hover { background: var(--bg-card-hover); border-color: var(--border-accent); color: var(--text-primary); }
        .btn-danger { background: transparent; color: var(--accent-red); border-color: rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background: var(--accent-red-glow); border-color: var(--accent-red); }
        .btn-success { background: transparent; color: var(--accent-green); border-color: rgba(16, 185, 129, 0.3); }
        .btn-success:hover { background: var(--accent-green-glow); border-color: var(--accent-green); }
        .btn-sm { padding: 0.3rem 0.65rem; font-size: 0.78rem; }

        /* ── Forms ─────────────────────────────────────────────────── */
        .form-input { background: var(--bg-input); border: 1px solid var(--border); border-radius: var(--radius-xs); padding: 0.55rem 0.85rem; color: var(--text-primary); font-family: inherit; font-size: 0.85rem; transition: border-color var(--transition), box-shadow var(--transition); width: 100%; }
        .form-input:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px var(--accent-blue-glow); }
        .form-input::placeholder { color: var(--text-muted); }
        select.form-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b95b0' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.75rem center; padding-right: 2.25rem; }
        .filter-bar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }

        /* ── Table ─────────────────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        thead th { padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); text-align: left; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--bg-card); white-space: nowrap; }
        tbody tr { transition: background var(--transition); }
        tbody tr:hover { background: var(--bg-card-hover); }
        tbody td { padding: 0.85rem 1rem; border-bottom: 1px solid rgba(42, 49, 80, 0.5); font-size: 0.88rem; vertical-align: middle; }

        /* ── Status Badges ─────────────────────────────────────────── */
        .status-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 20px; white-space: nowrap; }
        .status-badge.pending    { background: var(--accent-amber-glow); color: var(--accent-amber); border: 1px solid rgba(245,158,11,0.3); }
        .status-badge.analyzing  { background: var(--accent-blue-glow);  color: var(--accent-blue);  border: 1px solid rgba(59,130,246,0.3); }
        .status-badge.analyzed   { background: var(--accent-purple-glow);color: var(--accent-purple);border: 1px solid rgba(139,92,246,0.3); }
        .status-badge.fix_generated { background: rgba(6,182,212,0.15);  color: var(--accent-cyan);  border: 1px solid rgba(6,182,212,0.3); }
        .status-badge.pr_created { background: var(--accent-green-glow); color: var(--accent-green); border: 1px solid rgba(16,185,129,0.3); }
        .status-badge.pr_merged  { background: rgba(52,211,153,0.15);   color: #34d399;              border: 1px solid rgba(52,211,153,0.3); }
        .status-badge.ignored    { background: rgba(100,116,139,0.15);  color: #94a3b8;              border: 1px solid rgba(100,116,139,0.3); }
        .status-badge.failed     { background: var(--accent-red-glow);   color: var(--accent-red);   border: 1px solid rgba(239,68,68,0.3); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .status-badge.analyzing .status-dot, .status-badge.pending .status-dot { animation: pulse-dot 1.5s ease-in-out infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(0.8); } }

        /* ── Confidence Bar ────────────────────────────────────────── */
        .confidence-bar { display: flex; align-items: center; gap: 0.5rem; }
        .confidence-track { width: 80px; height: 6px; background: var(--bg-primary); border-radius: 3px; overflow: hidden; }
        .confidence-fill { height: 100%; border-radius: 3px; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .confidence-fill.high   { background: linear-gradient(90deg, var(--accent-green), #34d399); }
        .confidence-fill.medium { background: linear-gradient(90deg, var(--accent-amber), #fbbf24); }
        .confidence-fill.low    { background: linear-gradient(90deg, var(--accent-red), #f87171); }
        .confidence-text { font-size: 0.8rem; font-weight: 600; font-family: 'JetBrains Mono', monospace; min-width: 36px; }

        /* ── Code Blocks ───────────────────────────────────────────── */
        .code-block { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1rem; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; line-height: 1.7; overflow-x: auto; color: #c9d1d9; white-space: pre-wrap; word-break: break-word; }

        /* ── Misc ──────────────────────────────────────────────────── */
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mono { font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; }
        .text-muted { color: var(--text-secondary); }
        .text-sm { font-size: 0.82rem; }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .pagination-wrap { display: flex; justify-content: center; padding: 1.25rem; gap: 0.35rem; }
        .pagination-wrap .page-link { padding: 0.4rem 0.75rem; border-radius: var(--radius-xs); font-size: 0.82rem; color: var(--text-secondary); border: 1px solid var(--border); transition: all var(--transition); }
        .pagination-wrap .page-link:hover, .pagination-wrap .page-link.active { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); }

        .flash-message { padding: 0.85rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.88rem; font-weight: 500; animation: flash-in 0.3s ease-out; }
        .flash-message.success { background: var(--accent-green-glow); color: var(--accent-green); border: 1px solid rgba(16, 185, 129, 0.3); }
        @keyframes flash-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .detail-grid .full-width { grid-column: 1 / -1; }
        .detail-row { display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid rgba(42, 49, 80, 0.3); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 500; color: var(--text-secondary); font-size: 0.82rem; }
        .detail-value { font-weight: 500; text-align: right; }
        .section-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
        .action-group { display: flex; gap: 0.5rem; }

        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .detail-grid { grid-template-columns: 1fr; }
            .top-bar { padding: 0 1rem; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <header class="top-bar">
            <div class="logo">
                <div class="icon">🔭</div>
                <span>AutoDebug</span>
                <span class="badge">AI-Powered</span>
            </div>
            <nav>
                <a href="{{ route('autodebug.dashboard') }}" class="{{ request()->routeIs('autodebug.dashboard') ? 'active' : '' }}">Dashboard</a>
                @if(class_exists(\Laravel\Telescope\Telescope::class))
                    <a href="/telescope">Telescope</a>
                @endif
            </nav>
        </header>

        <main class="main-content">
            @if(session('success'))
                <div class="flash-message success">✅ {{ session('success') }}</div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
