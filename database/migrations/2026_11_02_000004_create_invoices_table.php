<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('environment')->comment('Ambiente de ARCA: homologacion o produccion');
            $table->unsignedSmallInteger('type')->comment('Código de comprobante de ARCA (11 = Factura C)');
            $table->unsignedInteger('point_of_sale');
            $table->unsignedInteger('number')->nullable()->comment('Se asigna al pedir el CAE');
            $table->date('issued_on')->nullable();
            $table->unsignedTinyInteger('concept')->comment('1 productos, 2 servicios, 3 ambos');
            $table->date('service_from')->nullable();
            $table->date('service_to')->nullable();
            $table->unsignedSmallInteger('recipient_document_type');
            $table->string('recipient_document_number', 11);
            $table->string('recipient_tax_condition');
            $table->string('recipient_name');
            $table->string('recipient_address')->nullable();
            $table->unsignedBigInteger('total')->comment('ARS en centavos');
            $table->string('status')->default('pendiente')->index();
            $table->string('cae', 14)->nullable();
            $table->date('cae_due_on')->nullable();
            $table->json('messages')->nullable()->comment('Errores y observaciones de ARCA');
            $table->json('arca_response')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'type', 'point_of_sale', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('detail')->nullable();
            $table->unsignedBigInteger('amount')->comment('ARS en centavos');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
