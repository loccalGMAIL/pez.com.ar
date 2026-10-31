<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('kind')->default('proveedor')->after('name')->index();
            $table->string('channel')->nullable()->after('kind')->comment('Dónde se le compró a un particular');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('service_item_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('unit_cost')->nullable()->after('unit_price')->comment('ARS en centavos, uso interno');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('work_order_id')->constrained()->nullOnDelete();
            $table->boolean('is_resale')->default(false)->after('notes');
            $table->unsignedBigInteger('cost')->nullable()->after('amount')->comment('ARS en centavos, uso interno');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['is_resale', 'cost']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('unit_cost');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['kind', 'channel']);
        });
    }
};
