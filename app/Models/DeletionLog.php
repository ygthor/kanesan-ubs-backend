<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletionLog extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'deleted_data',
        'deleted_by',
    ];
}
