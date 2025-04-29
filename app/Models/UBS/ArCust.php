<?php

namespace App\Models\UBS;

use Illuminate\Database\Eloquent\Model;

class ArCust extends Model
{
    //
    protected $table = 'ubs_ubsacc2015_arcust';
    protected $primaryKey = 'CUSTNO';
    protected $keyType = 'string';
    public $timestamps = false;
    public $incrementing = false;

    public $guarded = [];
}
