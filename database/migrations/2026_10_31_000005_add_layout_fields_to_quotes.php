<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('title')->nullable()->after('client_id');
            $table->string('warranty')->nullable()->after('notes');
            $table->string('payment_terms')->nullable()->after('warranty');
            $table->string('delivery_time')->nullable()->after('payment_terms');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('title')->nullable()->after('service_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['title', 'warranty', 'payment_terms', 'delivery_time']);
        });
    }
};
