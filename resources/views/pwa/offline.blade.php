<!doctype html>
<html lang="{{ config('pwa.lang') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('pwa.theme_color') }}" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ config('pwa.theme_color_dark') }}" media="(prefers-color-scheme: dark)">
    <title>Sin conexión | {{ \App\Http\Controllers\PwaController::appName() }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f9fafb;
            --text: #111827;
            --muted: #6b7280;
            --button: #059669;
            --button-text: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111827;
                --text: #f9fafb;
                --muted: #9ca3af;
                --button: #10b981;
                --button-text: #052e16;
            }
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            padding: calc(1rem + env(safe-area-inset-top)) calc(1rem + env(safe-area-inset-right)) calc(1rem + env(safe-area-inset-bottom)) calc(1rem + env(safe-area-inset-left));
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            text-align: center;
        }

        img {
            width: 96px;
            height: 96px;
            border-radius: 1.25rem;
        }

        h1 {
            margin: 1.5rem 0 .5rem;
            font-size: 1.5rem;
        }

        p {
            margin: 0 0 1.5rem;
            color: var(--muted);
        }

        button {
            padding: .75rem 1.5rem;
            border: 0;
            border-radius: .5rem;
            background: var(--button);
            color: var(--button-text);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main>
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('icons/icon-192.png'))) }}" alt="{{ \App\Http\Controllers\PwaController::appName() }}">
        <h1>Sin conexión</h1>
        <p>No hay conexión a internet. Revisá la red y volvé a intentar.</p>
        <button type="button" onclick="location.reload()">Reintentar</button>
    </main>
</body>
</html>
