<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reclamos_rto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('Rto_id')->constrained('rto');
            $table->text('producto');
            $table->decimal('cantidad', 10, 2);
            $table->text('observaciones')->nullable();
            $table->enum('estadoReclamoRto', ['pendiente', 'resuelto']);
            $table->text('resolucionReclamoRto')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

    }

    public function down()
    {
        Schema::dropIfExists('reclamos_rto');
    }
};