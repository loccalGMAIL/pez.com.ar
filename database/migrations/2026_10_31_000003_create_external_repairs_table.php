<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('specialty')->nullable()->comment('Rubro');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('external_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->text('problem');
            $table->string('supplier_reference')->nullable()->comment('Nro. de orden del proveedor');
            $table->string('status')->default('enviado')->index();
            $table->date('sent_on');
            $table->date('returned_on')->nullable();
            $table->unsignedBigInteger('cost')->nullable()->comment('ARS en centavos');
            $table->unsignedBigInteger('price')->nullable()->comment('ARS en centavos, a cobrar al cliente');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_repairs');
        Schema::dropIfExists('suppliers');
    }
};
