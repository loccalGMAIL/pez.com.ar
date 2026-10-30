<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Installable app (PWA) of the Filament panel: manifest, service worker,
 * offline page and iOS splash screens. Served without session or CSRF so
 * nothing here carries a cookie the browser could cache.
 */
class PwaController extends Controller
{
    /**
     * iOS splash screens (portrait): pixel width, pixel height, CSS width,
     * CSS height and pixel ratio. Closed list so nobody can ask for a huge canvas.
     *
     * @var list<array{0: int, 1: int, 2: int, 3: int, 4: int}>
     */
    private const SPLASH_SCREENS = [
        [750, 1334, 375, 667, 2],
        [828, 1792, 414, 896, 2],
        [1125, 2436, 375, 812, 3],
        [1170, 2532, 390, 844, 3],
        [1179, 2556, 393, 852, 3],
        [1206, 2622, 402, 874, 3],
        [1242, 2208, 414, 736, 3],
        [1242, 2688, 414, 896, 3],
        [1284, 2778, 428, 926, 3],
        [1290, 2796, 430, 932, 3],
        [1320, 2868, 440, 956, 3],
    ];

    public function manifest(): JsonResponse
    {
        $name = self::appName();
        $startUrl = self::panelPath();

        $manifest = [
            'id' => $startUrl,
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => config('pwa.description'),
            'lang' => config('pwa.lang'),
            'start_url' => $startUrl,
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'any',
            'theme_color' => config('pwa.theme_color'),
            'background_color' => config('pwa.background_color'),
            'icons' => [
                ['src' => self::path(asset('icons/icon-192.png')), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::path(asset('icons/icon-512.png')), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::path(asset('icons/icon-512-maskable.png')), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => array_map(fn (array $shortcut): array => [
                'name' => $shortcut['name'],
                'short_name' => $shortcut['short_name'],
                'url' => self::path(url($shortcut['url'])),
            ], config('pwa.shortcuts', [])),
        ];

        $screenshots = config('pwa.screenshots', []);

        if (filled($screenshots)) {
            $manifest['screenshots'] = array_map(fn (array $screenshot): array => [
                ...$screenshot,
                'src' => self::path(asset($screenshot['src'])),
                'type' => 'image/png',
            ], $screenshots);
        }

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.sw', [
                'version' => self::version(),
                'cachePrefix' => config('pwa.cache_prefix'),
                'offlineUrl' => self::path(route('pwa.offline')),
            ])
            ->header('Content-Type', 'text/javascript; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function offline(): View
    {
        return view('pwa.offline');
    }

    public function splash(int $width, int $height): Response
    {
        abort_unless(collect(self::SPLASH_SCREENS)->contains(fn (array $screen): bool => $screen[0] === $width && $screen[1] === $height), 404);

        $canvas = imagecreatetruecolor($width, $height);
        [$red, $green, $blue] = sscanf(config('pwa.background_color'), '#%02x%02x%02x');
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $red, $green, $blue));

        $icon = imagecreatefrompng(public_path('icons/icon-512-maskable.png'));
        $size = intdiv(min($width, $height), 3);
        imagecopyresampled($canvas, $icon, intdiv($width - $size, 2), intdiv($height - $size, 2), 0, 0, $size, $size, imagesx($icon), imagesy($icon));

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: int, 4: int}>
     */
    public static function splashScreens(): array
    {
        return self::SPLASH_SCREENS;
    }

    public static function appName(): string
    {
        return config('pwa.name') ?: config('app.name');
    }

    /**
     * Configured version or, when empty, a hash of the Vite manifest so it
     * changes by itself on every build.
     */
    public static function version(): string
    {
        if (filled(config('pwa.version'))) {
            return (string) config('pwa.version');
        }

        $viteManifest = public_path('build/manifest.json');

        return is_file($viteManifest) ? substr((string) md5_file($viteManifest), 0, 12) : 'dev';
    }

    public static function panelPath(): string
    {
        return '/'.ltrim(Filament::getPanel(config('pwa.panel'))->getPath(), '/');
    }

    private static function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: '/';
    }
}
