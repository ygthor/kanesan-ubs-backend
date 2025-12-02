<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Carbon\Carbon;

class DateOnlyCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     * 
     * This handles TEXT columns that store dates as strings like "2019-06-22"
     * We parse them explicitly in the app timezone to prevent timezone conversion issues
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return \Carbon\Carbon|null
     */
    public function get($model, $key, $value, $attributes)
    {
        if ($value === null) {
            return null;
        }

        // If it's already a Carbon instance, return as-is
        if ($value instanceof Carbon) {
            return $value;
        }

        // Parse the date string explicitly in app timezone at start of day
        // This prevents timezone conversion issues with TEXT date columns
        try {
            // If it's a date-only string (YYYY-MM-DD), parse it in app timezone
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))
                    ->startOfDay();
            }
            
            // If it includes time, parse and set to start of day in app timezone
            return Carbon::parse($value, config('app.timezone'))->startOfDay();
        } catch (\Exception $e) {
            \Log::warning("Failed to parse date: {$value} for key: {$key}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return string|null
     */
    public function set($model, $key, $value, $attributes)
    {
        if ($value === null) {
            return null;
        }

        // Convert to date-only string (YYYY-MM-DD) for TEXT column
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        // If it's a string, try to parse and format
        if (is_string($value)) {
            try {
                // Parse in app timezone and return date-only
                return Carbon::parse($value, config('app.timezone'))
                    ->startOfDay()
                    ->format('Y-m-d');
            } catch (\Exception $e) {
                \Log::warning("Failed to set date: {$value} for key: {$key}", [
                    'error' => $e->getMessage()
                ]);
                return $value; // Return as-is if parsing fails
            }
        }

        return $value;
    }
}
