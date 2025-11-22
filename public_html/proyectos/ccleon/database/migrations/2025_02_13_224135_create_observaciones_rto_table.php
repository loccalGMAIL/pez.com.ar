<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('observaciones_rto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('Rto_id')->constrained('rto');
            $table->text('descripcionObservacionesRto');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    public function down()
    {
        Schema::dropIfExists('observaciones_rto');
    }
};