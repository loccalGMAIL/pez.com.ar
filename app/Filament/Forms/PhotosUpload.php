<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\FileUpload;

/**
 * Optional photos, private and downscaled in the browser before uploading so
 * phone pictures stay small. On phones the picker offers the camera.
 */
class PhotosUpload
{
    public static function make(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->multiple()
            ->image()
            ->disk('local')
            ->directory($directory)
            ->visibility('private')
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth(1600)
            ->imageResizeTargetHeight(1600)
            ->imageResizeUpscale(false)
            ->maxSize(10240)
            ->maxFiles(12)
            ->panelLayout('grid')
            ->reorderable()
            ->openable()
            ->downloadable();
    }
}
