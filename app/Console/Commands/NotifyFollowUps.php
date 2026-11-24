<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Support\Push\PushNotifier;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Runs every minute on business days and notifies once a day, from the hour set
 * in Configuración → Seguimientos: a missed cron minute only delays the notice.
 */
class NotifyFollowUps extends Command
{
    protected $signature = 'follow-ups:notify {--force : Avisar aunque no sea la hora o ya se haya avisado hoy}';

    protected $description = 'Avisa en el celular cuántos seguimientos postventa hay para enviar hoy';

    private const NOTIFIED_ON_KEY = 'follow-ups.notified-on';

    public function handle(PushNotifier $notifier): int
    {
        if (! $this->option('force') && ! $this->isTime()) {
            return self::SUCCESS;
        }

        // Marked before sending, so a failing push service is not retried every minute.
        Cache::put(self::NOTIFIED_ON_KEY, today()->toDateString(), now()->endOfDay());

        $due = FollowUp::query()->due()->with('client')->get();

        if ($due->isEmpty()) {
            $this->info('No hay seguimientos para enviar hoy.');

            return self::SUCCESS;
        }

        $delivered = $notifier->sendToEveryone('Seguimientos postventa', self::message($due), '/', 'follow-ups');

        $this->info("Avisos enviados: {$delivered}");

        return self::SUCCESS;
    }

    /**
     * "Tenés 3 seguimientos para enviar: Estudio Pérez, Ana López y 1 más."
     *
     * @param  Collection<int, FollowUp>  $due
     */
    public static function message(Collection $due): string
    {
        $count = $due->count();
        $clients = $due->map(fn (FollowUp $followUp) => $followUp->client->trade_name ?: $followUp->client->business_name)->unique()->values();

        $list = $clients->count() > 2
            ? $clients->take(2)->join(', ').' y '.($clients->count() - 2).' más'
            : $clients->join(', ', ' y ');

        return $count === 1
            ? "Tenés 1 seguimiento para enviar: {$list}."
            : "Tenés {$count} seguimientos para enviar: {$list}.";
    }

    private function isTime(): bool
    {
        $time = Settings::get('follow_ups.notify_at');

        return FollowUp::isEnabled()
            && filled($time)
            && now()->gte(today()->setTimeFromTimeString($time))
            && Cache::get(self::NOTIFIED_ON_KEY) !== today()->toDateString();
    }
}
