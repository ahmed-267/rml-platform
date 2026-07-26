<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Error' }} — {{ config('app.name', 'RML Platform') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #16a34a;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(22, 163, 74, 0.12), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(13, 148, 136, 0.10), transparent 50%),
                var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            width: 100%;
            max-width: 32rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem 1.75rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            text-align: center;
        }
        .code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 4.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(22, 163, 74, 0.12);
            color: var(--primary);
            font-weight: 700;
            font-size: 0.875rem;
            letter-spacing: 0.04em;
        }
        h1 {
            margin: 1rem 0 0.5rem;
            font-size: 1.5rem;
            line-height: 1.25;
        }
        p {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.55;
        }
        .actions {
            margin-top: 1.75rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.65rem;
            padding: 0.65rem 1.1rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.15s ease;
        }
        a.btn-primary {
            background: linear-gradient(90deg, #16a34a, #0d9488);
            color: #fff;
        }
        a.btn-primary:hover { filter: brightness(1.05); }
        a.btn-ghost {
            border: 1px solid var(--border);
            color: var(--text);
            background: #fff;
        }
        a.btn-ghost:hover { background: #f8fafc; }
        .brand {
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="code">{{ $code ?? 'Error' }}</div>
        <h1>{{ $title ?? 'Something went wrong' }}</h1>
        <p>{{ $message ?? 'Please try again or return to the home page.' }}</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">{{ __('rml.common.back_to_home') }}</a>
            @auth
                <a class="btn btn-ghost" href="{{ url('/dashboard') }}">Dashboard</a>
            @endauth
        </div>
        <p class="brand">{{ __('rml.brand.footer_brand') }}</p>
    </main>
</body>
</html>
