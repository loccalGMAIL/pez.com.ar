<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedores_id')->constrained('proveedores');
            $table->integer('camion');
            $table->string('nroFacturaRto', 50);
            $table->date('fechaIngresoRto');
            $table->enum('estado', ['Espera', 'Deuda', 'Pagado'])->default('Espera');
            $table->decimal('totalTempRto', 10, 2)->nullable();
            $table->decimal('totalFinalRto', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

   }

    public function down()
    {
        Schema::dropIfExists('rto');
    }
};