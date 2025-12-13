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
     * Override date serialization to return datetime in app timezone (UTC+8)
     * This ensures dates are serialized in Asia/Kuala_Lumpur timezone instead of UTC
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        if ($date instanceof Carbon) {
            // Convert to app timezone (UTC+8) and format as datetime
            return $date->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }
        // Fallback for other DateTimeInterface implementations
        return Carbon::instance($date)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
