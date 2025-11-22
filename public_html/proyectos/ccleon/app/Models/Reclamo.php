<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reclamo extends Model
{
    protected $table = 'reclamos_rto';
    protected $primaryKey = 'id';
    protected $fillable = ['id', 
                        'Rto_id',
                        'producto',
                        'cantidad', 
                        'observaciones', 
                        'estadoReclamoRto',
                        'resolucionReclamoRto',
                        'created_at', 
                        'updated_at'];
    protected $hidden = ['created_at', 'updated_at'];
    public $timestamps = true;

    public function rto()
    {
        return $this->belongsTo(rto::class, 'Rto_id');
    }
}
