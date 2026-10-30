<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Command;

class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Marca como vencidos los presupuestos enviados cuya validez ya pasó';

    public function handle(): int
    {
        $count = 0;

        Quote::expirable()->each(function (Quote $quote) use (&$count) {
            $quote->expire();
            $count++;
        });

        $this->info("Presupuestos vencidos: {$count}");

        return self::SUCCESS;
    }
}
