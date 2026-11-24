<x-filament::dropdown.list.item icon="heroicon-o-arrow-down-tray" data-pwa-install style="display: none;">Instalar app</x-filament::dropdown.list.item>
@if (\App\Support\Push\PushNotifier::isConfigured())
    <x-filament::dropdown.list.item icon="heroicon-o-bell" data-push-toggle style="display: none;">Activar notificaciones</x-filament::dropdown.list.item>
@endif
