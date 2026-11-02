<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // A payment can have an invoice, its credit note and a new invoice,
            // and deleting the payment must never take a fiscal document with it.
            $table->dropUnique(['payment_id']);
            $table->dropForeign(['payment_id']);
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreignId('charge_id')->nullable()->after('payment_id')->comment('Factura emitida desde el cargo, antes de cobrar')->constrained()->nullOnDelete();
            $table->foreignId('credited_invoice_id')->nullable()->after('charge_id')->comment('En una nota de crédito, la factura que anula')->constrained('invoices')->restrictOnDelete();
            $table->date('payment_due_on')->nullable()->after('service_to');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('charge_id')->nullable()->after('invoice_id')->comment('Cargo que factura el ítem')->constrained()->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('invoice_before_payment')->default(false)->after('is_active')->comment('El cliente necesita la factura para pagar');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('invoice_before_payment');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credited_invoice_id');
            $table->dropConstrainedForeignId('charge_id');
            $table->dropColumn('payment_due_on');
            $table->dropForeign(['payment_id']);
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->unique('payment_id');
        });
    }
};
