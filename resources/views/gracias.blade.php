<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mensaje enviado — PEZ Servicios IT</title>
    <meta name="description" content="Tu mensaje fue recibido. Te contactamos en menos de 24 horas.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/landing.css') }}">
    <style>
        .gracias-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
        }
        .gracias-box {
            max-width: 540px;
            width: 100%;
            text-align: center;
            background: linear-gradient(150deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01));
            border: 1px solid rgba(0,255,119,0.25);
            border-radius: 20px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        }
        .gracias-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(0,255,119,0.12);
            margin-bottom: 24px;
        }
        .gracias-icon svg {
            width: 32px;
            height: 32px;
        }
        .gracias-box h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            margin: 0 0 12px;
            line-height: 1.2;
        }
        .gracias-box p {
            color: var(--muted);
            margin: 0 0 32px;
            font-size: 1.05rem;
        }
        .brand-back {
            display: block;
            margin-bottom: 32px;
        }
        .brand-back img {
            height: 48px;
            width: auto;
        }
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="gracias-wrap">
        <div class="gracias-box">
            <a href="/" class="brand-back">
                <img src="{{ asset('foto-de-perfil-A85EQ4bqJ4CWQP9N.png') }}" alt="PEZ Servicios IT">
            </a>
            <div class="gracias-icon">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 16L13 23L26 9" stroke="#00ff77" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1>¡Mensaje recibido!</h1>
            <p>Gracias por escribirnos. Te respondemos en menos de 24&nbsp;horas con claridad y sin vueltas.</p>
            <a class="btn primary" href="/">Volver al inicio</a>
        </div>
    </div>
</body>
</html>
