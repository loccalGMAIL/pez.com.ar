<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#092E35">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="PEZ Sistema">
<link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
<script>
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.pezInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('pez-installable'));
    });

    window.addEventListener('appinstalled', () => {
        window.pezInstallPrompt = null;
        window.dispatchEvent(new CustomEvent('pez-installed'));
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
</script>
