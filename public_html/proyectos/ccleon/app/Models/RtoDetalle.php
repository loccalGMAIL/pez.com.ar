<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RtoDetalle extends Model
{
    protected $table = 'rto_detalle';
    protected $primaryKey = 'id';
    protected $fillable = [
        'rto_id',
        'elementoRto_id',
        'valorDolaresRtoTeorico',
        'valorPesosRtoTeorico',
        'TC_RtoTeorico',
        'subTotalRtoTeorico',
        'valorPesosRtoReal',
        'TC_RtoReal',
        'subTotalRtoReal',
    ];

    protected $softDelete = true;
    
    public function rto()
    {
        return $this->belongsTo(Rto::class, 'rto_id');
    }

    public function elemento()
    {
        return $this->belongsTo(ElementoRto::class, 'elementoRto_id');
    }
    
}
