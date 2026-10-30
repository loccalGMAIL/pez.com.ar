<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->json('installment_plans')->nullable()->after('total')
                ->comment('Planes de cuotas ofrecidos: [{"count": 3, "surcharge": 15.5}]');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('installment_plans');
        });
    }
};
