<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElementoRto extends Model
{
    protected $table = 'elementos_rto';
    protected $primaryKey = 'id';
    protected $fillable = ['rto_id', 'descripcionElementoRto'];
    public $timestamps = false;

    public function rto()
    {
        return $this->belongsTo(Rto::class, 'rto_id');
    }


}
