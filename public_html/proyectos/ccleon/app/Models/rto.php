<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class rto extends Model
{
    protected $table = 'rto';
    protected $primaryKey = 'id';
    protected $fillable = [
        'proveedores_id',
        'camion',
        'nroFacturaRto',
        'fechaIngresoRto',
        'estado',
        'totalTempRto',
        'totalFinalRto',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedores_id');
    }

    public function camion()
    {
        return $this->belongsTo(Camion::class, 'id');
    }

    public function observaciones()
    {
        return $this->hasMany(Observacion::class, 'Rto_id');
    }
    public function reclamos()
    {
        return $this->hasMany(Reclamo::class, 'Rto_id');
    }
}
