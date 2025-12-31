<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\DeletionLog;
use Carbon\Carbon;

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

    /**
     * Override date serialization to return datetime in simple format (Y-m-d H:i:s)
     * Laravel's default serializeDate returns ISO 8601 format with timezone (e.g., 2025-12-30T03:25:14.000000Z)
     * This override formats dates as simple string (e.g., 2025-12-30 03:25:14) which the mobile app expects
     * Dates are already in app timezone (Asia/Kuala_Lumpur) from Laravel's datetime cast
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        // Just format the date - no timezone conversion needed since it's already in app timezone
        return $date->format('Y-m-d H:i:s');
    }
}
