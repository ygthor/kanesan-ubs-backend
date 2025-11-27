<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class Icgroup extends BaseModel
{
    use HasFactory;
    
    protected $table = 'icgroup';
    
    const CREATED_AT = 'CREATED_ON';
    const UPDATED_AT = 'UPDATED_ON';

    protected $fillable = [
        'name',
        'description',
        'CREATED_BY',
        'UPDATED_BY',
        'CREATED_ON',
        'UPDATED_ON',
    ];

    protected $casts = [
        'CREATED_ON' => 'datetime',
        'UPDATED_ON' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->visible = [
            ...$this->fillable,
        ];
    }
}


