<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id';
    protected $fillable = [
        'nombreProveedor',
        'dniProveedor',
        'razonSocialProveedor',
        'cuitProveedor',
        'telefonoProveedor',
        'mailProveedor',
        'direccionProveedor',
        'estadoProveedor'
    ];
    public $timestamps = true;
    protected $softDelete = true;
    
    public function camiones()
{
    return $this->hasMany(Camion::class, 'proveedores_id', 'id');
}
}
