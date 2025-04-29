<?php

namespace App\Traits;

use App\Models\UBSChangeLogsMaster;
use App\Models\UBSChangeLogsDetail;

trait LogsModelChanges
{
    public static function bootLogsModelChanges()
    {
        static::updating(function ($model) {
            $original = $model->getOriginal();
            $changes = $model->getDirty();

            $master = UBSChangeLogsMaster::create([
                'ubs_table'      => $model->getTable(),
                'key_field'  => $model->getKeyName(),
                'key_value'  => $model->getKey(),
                'action'  => 'update',
                'original_data' => json_encode(array_intersect_key($original, $changes)),
                'changes_data'     => json_encode($changes),
                'created_at' => now(),
                'created_by' => auth()->user() ? auth()->user()->id : null,
            ]);

            foreach ($changes as $field => $newValue) {
                UBSChangeLogsDetail::create([
                    'master_id' => $master->id,
                    'ubs_table'    => $model->getTable(),
                    'key_field'    => $model->getKeyName(),
                    'key_value'    => $model->getKey(),
                    'target_field' => $field,
                    'old_value'    => $original[$field] ?? null,
                    'new_value'    => $newValue,
                ]);
            }
        });

        static::creating(function ($model) {
            $original = $model->getOriginal();
            $changes = $model->getDirty();

            $master = UBSChangeLogsMaster::create([
                'ubs_table'      => $model->getTable(),
                'key_field'  => $model->getKeyName(),
                'key_value'  => $model->getKey(),
                'action'  => 'create',
                'original_data' => null,
                'changes_data'  => json_encode($changes),
                'created_at' => now(),
                'created_by' => auth()->user() ? auth()->user()->id : null,
            ]);


            foreach ($model->attributesToArray() as $field => $newValue) {
                UBSChangeLogsDetail::create([
                    'master_id' => $master->id,
                    'ubs_table'    => $model->getTable(),
                    'key_field'    => $model->getKeyName(),
                    'key_value'    => $model->getKey(),
                    'target_field' => $field,
                    'old_value'    => null,
                    'new_value'    => $newValue,
                ]);
            }
        });

        static::deleting(function ($model) {
            $original = $model->getOriginal();
            $master = UBSChangeLogsMaster::create([
                'ubs_table'      => $model->getTable(),
                'key_field'  => $model->getKeyName(),
                'key_value'  => $model->getKey(),
                'action'  => 'delete',
                'original_data'  => json_encode($original),
                'created_at' => now(),
                'created_by' => auth()->user() ? auth()->user()->id : null,
            ]);
        });
    }
}
