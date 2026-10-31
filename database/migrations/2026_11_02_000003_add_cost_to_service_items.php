<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('condition')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cost')->nullable()->after('price')->comment('ARS en centavos, uso interno');
            $table->text('notes')->nullable()->after('description')->comment('Uso interno: links, referencias');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['cost', 'notes']);
        });
    }
};
