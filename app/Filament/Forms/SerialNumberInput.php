<?php

namespace App\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * Serial number field with a camera button that reads the barcode on the
 * device's sticker. The ~100 KB scanner script loads on first use only.
 */
class SerialNumberInput
{
    public static function make(string $name = 'serial_number'): TextInput
    {
        return TextInput::make($name)
            ->label('Número de serie')
            ->maxLength(255)
            ->suffixAction(
                Action::make('scanSerial')
                    ->icon(Heroicon::OutlinedCamera)
                    ->tooltip('Leer con la cámara')
                    ->alpineClickHandler(fn (TextInput $component) => self::scanScript($component->getStatePath())),
            );
    }

    private static function scanScript(string $statePath): string
    {
        // Rendered inside a double-quoted HTML attribute: Js::from() emits
        // single-quoted strings, so the attribute is never cut short.
        $src = Js::from(FilamentAsset::getScriptSrc('serial-scanner'));
        $path = Js::from($statePath);

        return <<<JS
            (window.pezScanSerial
                ? Promise.resolve()
                : new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = {$src};
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.append(script);
                }))
                .then(() => window.pezScanSerial())
                .then((code) => \$wire.set({$path}, code))
                .catch(() => {})
            JS;
    }
}
