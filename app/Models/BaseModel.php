<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\DeletionLog;

class BaseModel extends Model
{
    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            // Log the deleted row
            DeletionLog::create([
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'deleted_data' => json_encode($model->toArray()),
                'deleted_by' => Auth::check() ? Auth::user()->id : null,
            ]);
        });
    }
}
