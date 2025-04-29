<?php

namespace App\Models\UBS;

use App\Traits\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;

class AccArCust extends Model
{
    use LogsModelChanges;

    protected $table = 'ubs_ubsacc2015_arcust';
    protected $guarded  = [];
    protected $primaryKey = 'CUSTNO';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;




    
}
