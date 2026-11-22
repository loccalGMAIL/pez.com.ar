<?php

namespace App\Console\Commands;

use App\Support\Push\PushNotifier;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeys extends Command
{
    protected $signature = 'push:keys';

    protected $description = 'Genera las claves VAPID de las notificaciones push para copiar al .env';

    public function handle(): int
    {
        if (PushNotifier::isConfigured()) {
            $this->warn('Ya hay claves en el .env: si las cambiás, cada dispositivo tiene que activar las notificaciones de nuevo.');
        }

        $keys = VAPID::createVapidKeys();

        $this->line('Agregá estas líneas al .env y después corré php artisan optimize:');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");

        return self::SUCCESS;
    }
}
