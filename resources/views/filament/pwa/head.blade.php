@php
    use App\Http\Controllers\PwaController;
@endphp

<link rel="manifest" href="{{ route('pwa.manifest', absolute: false) }}">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="{{ config('pwa.theme_color') }}" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="{{ config('pwa.theme_color_dark') }}" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ PwaController::appName() }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

@foreach (PwaController::splashScreens() as [$width, $height, $cssWidth, $cssHeight, $ratio])
    <link rel="apple-touch-startup-image" href="{{ route('pwa.splash', ['width' => $width, 'height' => $height], absolute: false) }}" media="(device-width: {{ $cssWidth }}px) and (device-height: {{ $cssHeight }}px) and (-webkit-device-pixel-ratio: {{ $ratio }}) and (orientation: portrait)">
@endforeach

<script>
    // Chrome may fire this before any script at the end of the body runs.
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('pwa:installable'));
    });
</script>

<style>
    @media (display-mode: standalone) {
        .fi-topbar {
            min-height: calc(var(--topbar-height) + env(safe-area-inset-top));
            padding-top: env(safe-area-inset-top);
            padding-left: calc(var(--spacing) * 4 + env(safe-area-inset-left));
            padding-right: calc(var(--spacing) * 4 + env(safe-area-inset-right));
        }

        .fi-main {
            padding-bottom: env(safe-area-inset-bottom);
        }

        .fi-sidebar {
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            padding-left: env(safe-area-inset-left);
        }
    }
</style>
