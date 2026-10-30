<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Backups made every night by spatie/laravel-backup (database + uploaded files),
 * to download a copy off the server or make one on demand.
 */
class Backups extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $title = 'Backups';

    protected static ?string $slug = 'backups';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 2;

    public static function disk(): string
    {
        return config('backup.backup.destination.disks')[0];
    }

    /**
     * @return array<string, array{path: string, name: string, date: Carbon, size: int}>
     */
    public static function backups(): array
    {
        $storage = Storage::disk(self::disk());

        return collect($storage->files(config('backup.backup.name')))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'path' => $path,
                'name' => basename($path),
                'date' => Carbon::createFromTimestamp($storage->lastModified($path))->setTimezone(config('app.timezone')),
                'size' => $storage->size($path),
            ])
            ->sortByDesc('date')
            ->keyBy('path')
            ->all();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => self::backups())
            ->description('Se hace uno automático todas las noches (base de datos y archivos subidos). Conviene descargar uno cada tanto y guardarlo fuera del servidor.')
            ->emptyStateHeading('Todavía no hay backups')
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (array $record) => $record['date']->diffForHumans()),
                TextColumn::make('size')
                    ->label('Tamaño')
                    ->formatStateUsing(fn (int $state) => Number::fileSize($state, precision: 1)),
                TextColumn::make('name')
                    ->label('Archivo')
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (array $record): StreamedResponse => Storage::disk(self::disk())->download($record['path'])),
                Action::make('delete')
                    ->label('Borrar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (array $record) => Storage::disk(self::disk())->delete($record['path'])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupNow')
                ->label('Hacer backup ahora')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Puede tardar unos segundos según el tamaño de la base y de los archivos.')
                ->action(function () {
                    $failed = Artisan::call('backup:run', ['--disable-notifications' => true]) !== 0;

                    $notification = Notification::make();

                    $failed
                        ? $notification->title('No se pudo hacer el backup')->body(str(Artisan::output())->trim()->afterLast("\n")->limit(300))->danger()->persistent()
                        : $notification->title('Backup hecho')->success();

                    $notification->send();
                }),
        ];
    }
}
