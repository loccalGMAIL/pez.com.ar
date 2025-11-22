<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camion extends Model
{
    protected $table = 'camiones';
    protected $primaryKey = 'id';
    protected $fillable = ['contador', 'proveedores_id'];
    public $timestamps = true;
    protected $softDelete = true;

    // En tu modelo Camion.php
public function proveedor()
{
    return $this->belongsTo(Proveedor::class, 'proveedores_id', 'id');
}
}
