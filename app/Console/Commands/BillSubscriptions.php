<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class BillSubscriptions extends Command
{
    protected $signature = 'subscriptions:bill';

    protected $description = 'Genera los cargos del mes de los abonos que llegaron a su día de facturación';

    public function handle(): int
    {
        $today = today();

        $created = Subscription::billableOn($today)->get()
            ->map(fn (Subscription $subscription) => $subscription->bill($today))
            ->filter();

        $this->info("Cargos generados: {$created->count()}");

        return self::SUCCESS;
    }
}
