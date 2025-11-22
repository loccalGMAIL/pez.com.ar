<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombreProveedor', 100);
            $table->string('dniProveedor', 20)->nullable();
            $table->string('razonSocialProveedor', 100);
            $table->string('cuitProveedor', 20);
            $table->string('telefonoProveedor', 20)->nullable();
            $table->string('mailProveedor', 100)->nullable();
            $table->string('direccionProveedor', 200)->nullable();
            $table->string('estadoProveedor', 50) ->default('1');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('proveedores');
    }
};