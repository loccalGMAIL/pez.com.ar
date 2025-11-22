<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    protected $table = 'observaciones_rto';
    protected $primaryKey = 'id';
    protected $fillable = ['id', 'Rto_id','descripcionObservacionesRto', 'created_at', 'updated_at'];
    protected $hidden = ['created_at', 'updated_at'];
    public $timestamps = true;

    public function rto()
    {
        return $this->belongsTo(rto::class, 'Rto_id');
    }
}
