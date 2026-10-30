<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('concept');
            $table->string('type');
            $table->unsignedBigInteger('amount')->comment('ARS en centavos; en los variables es el monto base');
            $table->unsignedTinyInteger('billing_day')->comment('Día del mes en que se genera el cargo (1-28)');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period')->nullable()->comment('Primer día del mes facturado (abonos)');
            $table->string('concept');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('amount')->comment('ARS en centavos');
            $table->unsignedBigInteger('paid')->default(0)->comment('ARS en centavos, suma de imputaciones');
            $table->date('issued_on');
            $table->date('due_on');
            $table->string('status')->default('pendiente')->index();
            $table->timestamps();

            $table->unique(['subscription_id', 'period']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount')->comment('ARS en centavos');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->comment('ARS en centavos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('charges');
        Schema::dropIfExists('subscriptions');
    }
};
